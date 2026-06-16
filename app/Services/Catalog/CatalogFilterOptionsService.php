<?php

namespace App\Services\Catalog;

use App\Models\DistributorProfile;
use App\Models\ManufacturerProfile;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CatalogFilterOptionsService
{
    public function __construct(
        private readonly CatalogQueryService $catalog,
    ) {}

    /**
     * @return Collection<int, DistributorProfile>
     */
    public function distributors(?ProductCategory $category = null, ?Builder $facetProductIdsQuery = null): Collection
    {
        if (! $this->catalog->isBuyerSideCatalog()) {
            return collect();
        }

        $regionId = $this->catalog->regionId();
        if ($regionId === null) {
            return collect();
        }

        $offers = $this->catalog->distributorOffers()->regionalOffersQuery();
        $catalogProductIds = $this->baseProductIdsQuery($category);

        $profileIds = (clone $offers)
            ->whereIn('source_product_id', $catalogProductIds)
            ->pluck('distributor_profile_id')
            ->unique()
            ->all();

        if ($profileIds === []) {
            return collect();
        }

        $profiles = DistributorProfile::query()
            ->whereIn('id', $profileIds)
            ->inRegion($regionId)
            ->orderBy('short_name')
            ->orderBy('full_name')
            ->get();

        if ($facetProductIdsQuery === null) {
            return $profiles;
        }

        $counts = (clone $offers)
            ->whereIn('source_product_id', (clone $facetProductIdsQuery)->select('products.id'))
            ->selectRaw('distributor_profile_id, COUNT(DISTINCT source_product_id) as facet_count')
            ->groupBy('distributor_profile_id')
            ->pluck('facet_count', 'distributor_profile_id');

        return $profiles->map(function (DistributorProfile $profile) use ($counts): DistributorProfile {
            $profile->setAttribute('facet_count', (int) ($counts[$profile->id] ?? 0));

            return $profile;
        });
    }

    /**
     * @return Collection<int, ManufacturerProfile>
     */
    public function manufacturers(?ProductCategory $category = null, ?Builder $facetProductIdsQuery = null): Collection
    {
        if ($this->catalog->catalogRole()?->slug === Role::SLUG_MANUFACTURER) {
            return collect();
        }

        $manufacturerIds = $this->baseProductIdsQuery($category)
            ->select('manufacturer_profile_id')
            ->distinct()
            ->pluck('manufacturer_profile_id')
            ->filter()
            ->all();

        if ($manufacturerIds === []) {
            return collect();
        }

        $profiles = ManufacturerProfile::query()
            ->whereIn('id', $manufacturerIds)
            ->orderBy('short_name')
            ->orderBy('full_name')
            ->get();

        if ($facetProductIdsQuery === null) {
            return $profiles;
        }

        $counts = (clone $facetProductIdsQuery)
            ->whereNotNull('manufacturer_profile_id')
            ->selectRaw('manufacturer_profile_id, COUNT(*) as facet_count')
            ->groupBy('manufacturer_profile_id')
            ->pluck('facet_count', 'manufacturer_profile_id');

        return $profiles->map(function (ManufacturerProfile $profile) use ($counts): ManufacturerProfile {
            $profile->setAttribute('facet_count', (int) ($counts[$profile->id] ?? 0));

            return $profile;
        });
    }

    /**
     * Диапазон цен для ползунка: по текущей выдаче без учёта выбранного price_min/price_max.
     *
     * @return array{min: float, max: float}|null
     */
    public function priceBounds(?ProductCategory $category = null): ?array
    {
        $role = $this->catalog->catalogRole()?->slug;
        $hasPriceFilter = match ($role) {
            Role::SLUG_MANUFACTURER, Role::SLUG_DISTRIBUTOR, Role::SLUG_END_COMPANY, Role::SLUG_COMPANY_EMPLOYEE => true,
            default => false,
        };

        if (! $hasPriceFilter) {
            return null;
        }

        $productIds = $this->baseProductIdsQuery($category);

        if ($this->catalog->isBuyerSideCatalog()) {
            $regionId = $this->catalog->regionId();
            if ($regionId === null) {
                return null;
            }

            $row = $this->catalog->distributorOffers()
                ->regionalOffersQuery()
                ->whereIn('source_product_id', $productIds)
                ->whereNotNull('retail_price')
                ->where('retail_price', '>', 0)
                ->selectRaw('MIN(retail_price) as min_price, MAX(retail_price) as max_price')
                ->first();
        } else {
            $row = Product::query()
                ->whereIn('products.id', $productIds)
                ->whereNotNull('base_price')
                ->where('base_price', '>', 0)
                ->selectRaw('MIN(base_price) as min_price, MAX(base_price) as max_price')
                ->first();
        }

        if ($row === null || $row->min_price === null || $row->max_price === null) {
            return null;
        }

        $min = (float) $row->min_price;
        $max = (float) $row->max_price;

        if ($max <= $min) {
            return null;
        }

        return [
            'min' => floor($min),
            'max' => ceil($max),
        ];
    }

    /**
     * @return Builder<Product>
     */
    private function baseProductIdsQuery(?ProductCategory $category): Builder
    {
        $query = $this->catalog->visibleProductsQuery();
        if ($category !== null) {
            $query->inCategory($category->id);
        }

        return $query->select('products.id');
    }
}
