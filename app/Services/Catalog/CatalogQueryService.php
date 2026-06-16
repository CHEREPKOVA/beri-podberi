<?php

namespace App\Services\Catalog;

use App\Models\DistributorProduct;
use App\Models\DistributorProfile;
use App\Models\ManufacturerDistributorPartnership;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

class CatalogQueryService
{
    public function __construct(
        private readonly User $user,
        private readonly ?int $catalogRegionId = null,
    ) {}

    public function catalogRole(): ?Role
    {
        return $this->user->getCurrentRole();
    }

    public function regionId(): ?int
    {
        return $this->catalogRegionId ?? $this->user->currentCompanyRegionId();
    }

    public function isEndCompanyCatalog(): bool
    {
        return in_array($this->catalogRole()?->slug, [Role::SLUG_END_COMPANY, Role::SLUG_COMPANY_EMPLOYEE], true);
    }

    public function isBuyerSideCatalog(): bool
    {
        return in_array($this->catalogRole()?->slug, [
            Role::SLUG_END_COMPANY,
            Role::SLUG_COMPANY_EMPLOYEE,
            Role::SLUG_DISTRIBUTOR,
        ], true);
    }

    public function distributorOffers(): EndCompanyDistributorOfferService
    {
        return new EndCompanyDistributorOfferService($this->regionId());
    }

    private function endCompanyVisibleProductsQuery(): Builder
    {
        $offers = $this->distributorOffers();
        $query = Product::query()->visibleInCatalog();

        if (EndCompanyCatalogSettings::showUnavailableProducts()) {
            return $query->where(function (Builder $builder) use ($offers): void {
                $builder
                    ->whereIn('products.id', $offers->purchasableProductsQuery()->select('products.id'))
                    ->orWhereIn('products.id', $offers->unavailableInRegionProductsQuery()->select('products.id'));
            });
        }

        return $query->whereIn('products.id', $offers->purchasableProductsQuery()->select('products.id'));
    }

    /** Базовый запрос видимых в каталоге товаров с учётом роли. */
    public function visibleProductsQuery(): Builder
    {
        $role = $this->catalogRole();

        if ($role?->slug === Role::SLUG_MANUFACTURER) {
            $profileId = $this->user->manufacturerProfile?->id;
            if ($profileId === null) {
                return Product::query()->whereRaw('1 = 0');
            }

            return Product::query()
                ->visibleInCatalog()
                ->forManufacturer($profileId);
        }

        if ($role?->slug === Role::SLUG_DISTRIBUTOR) {
            $partnerManufacturerIds = $this->activePartnerManufacturerIds();
            if ($partnerManufacturerIds === []) {
                return Product::query()->whereRaw('1 = 0');
            }

            return Product::query()
                ->visibleInCatalog()
                ->forRegion($this->regionId())
                ->whereIn('manufacturer_profile_id', $partnerManufacturerIds);
        }

        if (in_array($role?->slug, [Role::SLUG_END_COMPANY, Role::SLUG_COMPANY_EMPLOYEE], true)) {
            return $this->endCompanyVisibleProductsQuery();
        }

        return Product::query()
            ->visibleInCatalog()
            ->availableInRegion($this->regionId());
    }

    /**
     * @return list<int>
     */
    public function activePartnerManufacturerIds(): array
    {
        if ($this->catalogRole()?->slug !== Role::SLUG_DISTRIBUTOR) {
            return [];
        }

        $distributorProfileId = DistributorProfile::query()
            ->where('user_id', $this->user->id)
            ->value('id');

        if ($distributorProfileId === null) {
            return [];
        }

        return ManufacturerDistributorPartnership::query()
            ->where('distributor_profile_id', $distributorProfileId)
            ->where('status', ManufacturerDistributorPartnership::STATUS_ACTIVE)
            ->pluck('manufacturer_profile_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    public function manufacturerProfileIdForFilters(): ?int
    {
        if ($this->catalogRole()?->slug === Role::SLUG_MANUFACTURER) {
            return $this->user->manufacturerProfile?->id;
        }

        return null;
    }

    /** Дерево категорий без веток, в которых нет доступных товаров. */
    public function categoryTree(): Collection
    {
        $role = $this->catalogRole();
        $roleSlug = $role?->slug ?? 'any';
        $regionId = $this->regionId() ?? 0;
        $manufacturerProfileId = $this->manufacturerProfileIdForFilters() ?? 0;

        $cacheKey = sprintf(
            'catalog.category_tree:%s:region:%d:mfr:%d',
            $roleSlug,
            $regionId,
            $manufacturerProfileId,
        );
        $cacheKey = app(CatalogCacheService::class)->versionedKey($cacheKey);

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($role): Collection {
            $roots = ProductCategory::getTree(false, null, $role);

            return ProductCategory::filterTreeByProductVisibility(
                $roots,
                fn (array $categoryIds): bool => $this->hasProductsInCategories($categoryIds)
            );
        });
    }

    public function hasProductsInCategories(array $categoryIds): bool
    {
        if ($categoryIds === []) {
            return false;
        }

        return $this->visibleProductsQuery()
            ->inAnyCategoryIds($categoryIds)
            ->exists();
    }

    /**
     * Дистрибьюторы в регионе, у которых есть активная позиция по товару (для карточки КК).
     *
     * @return Collection<int, DistributorProfile>
     */
    public function distributorsForProductInRegion(Product $product): Collection
    {
        $regionId = $this->regionId();
        if ($regionId === null) {
            return collect();
        }

        $profileIds = DistributorProduct::query()
            ->where('source_product_id', $product->id)
            ->where('status', DistributorProduct::STATUS_ACTIVE)
            ->whereHas('profile', fn ($q) => $q->inRegion($regionId))
            ->whereHas('profile.manufacturerPartnerships', function ($pq) use ($product) {
                $pq->where('manufacturer_profile_id', $product->manufacturer_profile_id)
                    ->where('status', ManufacturerDistributorPartnership::STATUS_ACTIVE);
            })
            ->pluck('distributor_profile_id')
            ->unique()
            ->all();

        if ($profileIds === []) {
            return collect();
        }

        return DistributorProfile::query()
            ->whereIn('id', $profileIds)
            ->orderBy('short_name')
            ->orderBy('full_name')
            ->get();
    }

    /** Есть ли у товара аналоги, видимые в каталоге текущего пользователя. */
    public function hasVisibleAnalogs(Product $product): bool
    {
        $linkedAnalogs = $this->linkedAnalogCandidates($product);
        if ($linkedAnalogs->isEmpty()) {
            return false;
        }

        return $this->distributorOffers()->hasVisibleAnalogs($product, $linkedAnalogs);
    }

    /**
     * Аналоги, назначенные производителем и доступные в каталоге (регион, дистрибьютор, категория).
     *
     * @return Collection<int, Product>
     */
    public function resolveVisibleAnalogs(Product $product): Collection
    {
        $linkedAnalogs = $this->linkedAnalogCandidates($product);
        if ($linkedAnalogs->isEmpty()) {
            return collect();
        }

        $resolved = $this->distributorOffers()->resolveAnalogs($product, $linkedAnalogs);

        return $this->distributorOffers()->enrichProducts($resolved);
    }

    /**
     * @return Collection<int, Product>
     */
    private function linkedAnalogCandidates(Product $product): Collection
    {
        $analogIds = $product->allAnalogIds();
        if ($analogIds === []) {
            return collect();
        }

        return Product::query()
            ->visibleInCatalog()
            ->with([
                'category',
                'images',
                'manufacturerProfile.regions',
                'attributeValues.attribute',
                'additionalCategories',
            ])
            ->whereIn('products.id', $analogIds)
            ->where('products.id', '!=', $product->id)
            ->compatibleWithProduct($product)
            ->orderBy('name')
            ->get();
    }
}
