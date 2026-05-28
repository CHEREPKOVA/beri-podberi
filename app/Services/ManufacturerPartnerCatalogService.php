<?php

namespace App\Services;

use App\Models\DistributorProfile;
use App\Models\EndCompanyProfile;
use App\Models\ManufacturerDistributorExclusiveRegion;
use App\Models\ManufacturerDistributorPartnership;
use App\Models\ManufacturerDistributorPartnershipLog;
use App\Models\ManufacturerProfile;
use App\Models\PlatformOrder;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Region;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
class ManufacturerPartnerCatalogService
{
    public const CATALOG_DISTRIBUTORS = 'distributors';

    public const CATALOG_COMPANIES = 'companies';

    public const SESSION_CATALOG_TYPE = 'manufacturer_partner_catalog_type';

    public function resolveCatalogType(string $requested): string
    {
        if (in_array($requested, [self::CATALOG_DISTRIBUTORS, self::CATALOG_COMPANIES], true)) {
            session([self::SESSION_CATALOG_TYPE => $requested]);

            return $requested;
        }

        return session(self::SESSION_CATALOG_TYPE, self::CATALOG_DISTRIBUTORS);
    }

    /**
     * @return Collection<int, int>
     */
    public function manufacturerProductCategoryIds(ManufacturerProfile $manufacturer): Collection
    {
        return Product::query()
            ->where('manufacturer_profile_id', $manufacturer->id)
            ->whereNotNull('category_id')
            ->distinct()
            ->pluck('category_id');
    }

    /**
     * ID категорий производителя с предками (для сопоставления с профилем дистрибьютора).
     *
     * @return list<int>
     */
    public function manufacturerCategoryIdsForPartnerFilter(ManufacturerProfile $manufacturer): array
    {
        return $this->expandCategoryIdsWithAncestors(
            $this->manufacturerProductCategoryIds($manufacturer)->all()
        );
    }

    /**
     * @param  array<int|string>|Collection<int, int|string>  $ids
     * @return list<int>
     */
    public function expandCategoryIdsWithAncestors(array|Collection $ids): array
    {
        $expanded = collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($expanded->isEmpty()) {
            return [];
        }

        ProductCategory::query()
            ->whereIn('id', $expanded)
            ->get()
            ->each(function (ProductCategory $category) use ($expanded): void {
                foreach ($category->ancestorIds() as $ancestorId) {
                    $expanded->push($ancestorId);
                }
            });

        return $expanded->unique()->values()->all();
    }

    /**
     * @return Collection<int, ProductCategory>
     */
    public function filterableCategories(ManufacturerProfile $manufacturer): Collection
    {
        $ids = $this->manufacturerProductCategoryIds($manufacturer);

        return ProductCategory::query()
            ->whereIn('id', $ids)
            ->orWhereIn('id', function ($q) use ($ids) {
                $q->select('parent_id')
                    ->from('product_categories')
                    ->whereIn('id', $ids)
                    ->whereNotNull('parent_id');
            })
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->unique('id')
            ->values();
    }

    public function paginateDistributors(
        ManufacturerProfile $manufacturer,
        array $filters = [],
        string $sort = 'name',
        string $direction = 'asc',
    ): LengthAwarePaginator {
        $partnershipIds = ManufacturerDistributorPartnership::query()
            ->where('manufacturer_profile_id', $manufacturer->id)
            ->where('status', ManufacturerDistributorPartnership::STATUS_ACTIVE)
            ->pluck('distributor_profile_id');

        $exclusiveDistributorIds = ManufacturerDistributorExclusiveRegion::query()
            ->where('manufacturer_profile_id', $manufacturer->id)
            ->pluck('distributor_profile_id')
            ->unique();

        $query = DistributorProfile::query()
            ->visibleToManufacturer($manufacturer)
            ->withCompletePartnerProfile()
            ->with([
                'regions',
                'productCategories',
                'user',
            ])
            ->withCount(['platformOrders as orders_count']);

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $q) use ($search): void {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('short_name', 'like', "%{$search}%")
                    ->orWhere('inn', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['region_ids'])) {
            $regionIds = array_map('intval', (array) $filters['region_ids']);
            $query->whereHas('regions', fn (Builder $q) => $q->whereIn('regions.id', $regionIds));
        }

        $categoryIds = $filters['category_ids'] ?? null;
        $useDefaultCategoryFilter = $categoryIds === null && empty($filters['categories_reset']);

        if ($useDefaultCategoryFilter) {
            $categoryIds = $this->manufacturerCategoryIdsForPartnerFilter($manufacturer);
        }

        if (! empty($categoryIds)) {
            $expandedCategoryIds = $this->expandCategoryIdsWithAncestors((array) $categoryIds);

            $query->whereHas(
                'productCategories',
                fn (Builder $sub) => $sub->whereIn('product_categories.id', $expandedCategoryIds)
            );
        }

        $allowedSorts = [
            'name' => 'full_name',
            'registered_at' => 'created_at',
            'orders' => 'orders_count',
        ];
        $sortColumn = $allowedSorts[$sort] ?? 'full_name';
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        if ($sortColumn === 'orders_count') {
            $query->orderBy('orders_count', $direction);
        } else {
            $query->orderBy($sortColumn, $direction);
        }

        $paginator = $query->paginate(15)->withQueryString();

        $paginator->getCollection()->transform(function (DistributorProfile $distributor) use (
            $manufacturer,
            $partnershipIds,
            $exclusiveDistributorIds,
        ) {
            $distributor->setAttribute('partnership_status', $this->cooperationStatus(
                $manufacturer->id,
                $distributor->id,
                $partnershipIds->contains($distributor->id),
                $exclusiveDistributorIds->contains($distributor->id),
            ));

            return $distributor;
        });

        return $paginator;
    }

    public function cooperationStatus(
        int $manufacturerProfileId,
        int $distributorProfileId,
        ?bool $isPartner = null,
        ?bool $hasExclusive = null,
    ): string {
        if ($isPartner === null) {
            $isPartner = ManufacturerDistributorPartnership::query()
                ->where('manufacturer_profile_id', $manufacturerProfileId)
                ->where('distributor_profile_id', $distributorProfileId)
                ->where('status', ManufacturerDistributorPartnership::STATUS_ACTIVE)
                ->exists();
        }

        if ($hasExclusive === null) {
            $hasExclusive = ManufacturerDistributorExclusiveRegion::query()
                ->where('manufacturer_profile_id', $manufacturerProfileId)
                ->where('distributor_profile_id', $distributorProfileId)
                ->exists();
        }

        if ($hasExclusive) {
            return 'exclusive';
        }

        if ($isPartner) {
            return 'connected';
        }

        return 'not_connected';
    }

    public function cooperationStatusLabel(string $status): string
    {
        return match ($status) {
            'connected' => 'Подключен',
            'exclusive' => 'Эксклюзив в регионе',
            'blocked' => 'Заблокирован',
            default => 'Не подключен',
        };
    }

    public function detailCooperationLabel(string $status, bool $blocked = false): string
    {
        if ($blocked) {
            return 'Заблокирован';
        }

        return match ($status) {
            'exclusive' => 'Эксклюзивный дистрибьютор',
            'connected' => 'Активный партнёр',
            default => 'Не добавлен',
        };
    }

    public function paginateCompanies(array $filters = [], string $sort = 'name', string $direction = 'asc'): LengthAwarePaginator
    {
        $query = EndCompanyProfile::query()
            ->with(['user', 'deliveryAddresses.region'])
            ->withCount(['platformOrders as orders_count']);

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $q) use ($search): void {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('short_name', 'like', "%{$search}%")
                    ->orWhere('inn', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['region_ids'])) {
            $regionIds = array_map('intval', (array) $filters['region_ids']);
            $query->whereHas('deliveryAddresses', fn (Builder $q) => $q->whereIn('region_id', $regionIds));
        }

        $allowedSorts = [
            'name' => 'full_name',
            'registered_at' => 'created_at',
            'orders' => 'orders_count',
        ];
        $sortColumn = $allowedSorts[$sort] ?? 'full_name';
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        if ($sortColumn === 'orders_count') {
            $query->orderBy('orders_count', $direction);
        } else {
            $query->orderBy($sortColumn, $direction);
        }

        return $query->paginate(15)->withQueryString();
    }

    public function addToMyNetwork(ManufacturerProfile $manufacturer, DistributorProfile $distributor, User $user): ManufacturerDistributorPartnership
    {
        $partnership = ManufacturerDistributorPartnership::query()->updateOrCreate(
            [
                'manufacturer_profile_id' => $manufacturer->id,
                'distributor_profile_id' => $distributor->id,
            ],
            [
                'status' => ManufacturerDistributorPartnership::STATUS_ACTIVE,
                'added_by_user_id' => $user->id,
                'added_at' => now(),
            ],
        );

        $this->logAction(
            $manufacturer->id,
            $distributor->id,
            ManufacturerDistributorPartnershipLog::ACTION_ADDED,
            'Добавлен к моим',
            $user,
        );

        return $partnership;
    }

    public function removeFromMyNetwork(ManufacturerProfile $manufacturer, DistributorProfile $distributor, User $user): void
    {
        ManufacturerDistributorPartnership::query()
            ->where('manufacturer_profile_id', $manufacturer->id)
            ->where('distributor_profile_id', $distributor->id)
            ->delete();

        ManufacturerDistributorExclusiveRegion::query()
            ->where('manufacturer_profile_id', $manufacturer->id)
            ->where('distributor_profile_id', $distributor->id)
            ->delete();

        $this->logAction(
            $manufacturer->id,
            $distributor->id,
            ManufacturerDistributorPartnershipLog::ACTION_REMOVED,
            'Удален из списка',
            $user,
        );
    }

    /**
     * @param  array<int>  $regionIds
     * @return array{assigned: int, skipped: array<int, string>}
     */
    public function assignExclusiveRegions(
        ManufacturerProfile $manufacturer,
        DistributorProfile $distributor,
        array $regionIds,
        User $user,
    ): array {
        $skipped = [];
        $assigned = 0;

        foreach ($regionIds as $regionId) {
            $regionId = (int) $regionId;
            $region = Region::find($regionId);
            if (! $region) {
                continue;
            }

            $occupiedByManufacturer = ManufacturerDistributorExclusiveRegion::query()
                ->where('manufacturer_profile_id', $manufacturer->id)
                ->where('region_id', $regionId)
                ->where('distributor_profile_id', '!=', $distributor->id)
                ->exists();

            $occupiedByOtherManufacturer = ManufacturerDistributorExclusiveRegion::query()
                ->where('region_id', $regionId)
                ->where('distributor_profile_id', $distributor->id)
                ->where('manufacturer_profile_id', '!=', $manufacturer->id)
                ->exists();

            if ($occupiedByManufacturer || $occupiedByOtherManufacturer) {
                $skipped[$regionId] = 'Регион недоступен для назначения эксклюзивности.';

                continue;
            }

            ManufacturerDistributorExclusiveRegion::query()->updateOrCreate(
                [
                    'manufacturer_profile_id' => $manufacturer->id,
                    'region_id' => $regionId,
                ],
                [
                    'distributor_profile_id' => $distributor->id,
                    'assigned_by_user_id' => $user->id,
                ],
            );

            $this->logAction(
                $manufacturer->id,
                $distributor->id,
                ManufacturerDistributorPartnershipLog::ACTION_EXCLUSIVE,
                "Назначен эксклюзивным в регионе {$region->name}",
                $user,
                ['region_id' => $regionId],
            );

            $assigned++;
        }

        return ['assigned' => $assigned, 'skipped' => $skipped];
    }

    /**
     * @return Collection<int, Region>
     */
    public function availableExclusiveRegions(ManufacturerProfile $manufacturer, DistributorProfile $distributor): Collection
    {
        $blockedRegionIds = ManufacturerDistributorExclusiveRegion::query()
            ->where('manufacturer_profile_id', $manufacturer->id)
            ->where('distributor_profile_id', '!=', $distributor->id)
            ->pluck('region_id');

        $blockedForDistributor = ManufacturerDistributorExclusiveRegion::query()
            ->where('distributor_profile_id', $distributor->id)
            ->where('manufacturer_profile_id', '!=', $manufacturer->id)
            ->pluck('region_id');

        $excludeIds = $blockedRegionIds->merge($blockedForDistributor)->unique();

        return Region::active()
            ->orderBy('name')
            ->whereNotIn('id', $excludeIds)
            ->get();
    }

    /**
     * @return Collection<int, ManufacturerDistributorExclusiveRegion>
     */
    public function exclusiveRegionsForPair(ManufacturerProfile $manufacturer, DistributorProfile $distributor): Collection
    {
        return ManufacturerDistributorExclusiveRegion::query()
            ->with('region')
            ->where('manufacturer_profile_id', $manufacturer->id)
            ->where('distributor_profile_id', $distributor->id)
            ->get();
    }

    public function distributorOrders(DistributorProfile $distributor, ManufacturerProfile $viewerManufacturer): LengthAwarePaginator
    {
        return PlatformOrder::query()
            ->where('distributor_profile_id', $distributor->id)
            ->orderByDesc('ordered_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->through(function (PlatformOrder $order) use ($viewerManufacturer) {
                $order->setAttribute(
                    'amount_visible',
                    $order->manufacturer_profile_id === null
                        || (int) $order->manufacturer_profile_id === (int) $viewerManufacturer->id
                );

                return $order;
            });
    }

    public function logAction(
        int $manufacturerProfileId,
        int $distributorProfileId,
        string $action,
        string $description,
        User $user,
        array $meta = [],
    ): void {
        ManufacturerDistributorPartnershipLog::create([
            'manufacturer_profile_id' => $manufacturerProfileId,
            'distributor_profile_id' => $distributorProfileId,
            'action' => $action,
            'description' => $description,
            'performed_by_user_id' => $user->id,
            'meta' => $meta ?: null,
        ]);
    }

    public function partnershipLogs(ManufacturerProfile $manufacturer, DistributorProfile $distributor): Collection
    {
        return ManufacturerDistributorPartnershipLog::query()
            ->with('performedByUser')
            ->where('manufacturer_profile_id', $manufacturer->id)
            ->where('distributor_profile_id', $distributor->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    public function isPartner(ManufacturerProfile $manufacturer, DistributorProfile $distributor): bool
    {
        return ManufacturerDistributorPartnership::query()
            ->where('manufacturer_profile_id', $manufacturer->id)
            ->where('distributor_profile_id', $distributor->id)
            ->where('status', ManufacturerDistributorPartnership::STATUS_ACTIVE)
            ->exists();
    }
}
