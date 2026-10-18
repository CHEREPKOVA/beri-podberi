<?php

namespace App\Services\Cart;

use App\Models\DeliveryMethod;
use App\Models\DistributorProduct;
use App\Models\DistributorProfile;
use Illuminate\Support\Collection;

class CartSplitService
{
    public const PRICE_SCOPE_REGIONAL = 'regional';

    public const PRICE_SCOPE_BASE = 'base';

    /**
     * @param  Collection<int, array<string, mixed>>  $enrichedItems
     * @return array{
     *     groups: Collection<int, array<string, mixed>>,
     *     blocked_items: Collection<int, array<string, mixed>>
     * }
     */
    public function build(Collection $enrichedItems): array
    {
        $blocked = $enrichedItems
            ->filter(fn (array $row): bool => $this->hasBlockingWarning($row))
            ->values();

        $placeable = $enrichedItems
            ->reject(fn (array $row): bool => $this->hasBlockingWarning($row))
            ->values();

        $groups = $placeable
            ->groupBy(fn (array $row): string => $this->groupKey($row))
            ->map(fn (Collection $groupItems): array => $this->mapGroup($groupItems))
            ->values();

        return [
            'groups' => $this->decorateDeliveryMethods($groups),
            'blocked_items' => $blocked,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function groupKey(array $item): string
    {
        return implode('|', [
            (int) ($item['distributor_profile_id'] ?? 0),
            (int) ($item['region_id'] ?? 0),
            (int) ($item['distributor_warehouse_id'] ?? 0),
            (string) ($item['price_scope'] ?? self::PRICE_SCOPE_BASE),
        ]);
    }

    public function resolvePriceScope(?DistributorProduct $offer, ?int $regionId): string
    {
        if ($offer === null || $regionId === null) {
            return self::PRICE_SCOPE_BASE;
        }

        $regional = $offer->relationLoaded('regionalPrices')
            ? $offer->regionalPrices->firstWhere('region_id', $regionId)
            : $offer->regionalPrices()->where('region_id', $regionId)->first();

        if ($regional !== null && $regional->price !== null) {
            return self::PRICE_SCOPE_REGIONAL;
        }

        return self::PRICE_SCOPE_BASE;
    }

    public function priceScopeLabel(string $priceScope): string
    {
        return $priceScope === self::PRICE_SCOPE_REGIONAL
            ? 'Региональная цена'
            : 'Базовая цена';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function hasBlockingWarning(array $row): bool
    {
        return collect($row['warnings'] ?? [])->contains(
            fn (array $warning): bool => (bool) ($warning['blocking'] ?? false)
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $groupItems
     * @return array<string, mixed>
     */
    private function mapGroup(Collection $groupItems): array
    {
        $first = $groupItems->first() ?? [];
        $priceScope = (string) ($first['price_scope'] ?? self::PRICE_SCOPE_BASE);
        $warehouseName = $first['warehouse_name'] ?? null;
        $regionName = $first['region_name'] ?? null;
        $distributorName = $first['distributor_name'] ?? 'Поставщик';

        return [
            'group_key' => $this->groupKey($first),
            'distributor_profile_id' => (int) ($first['distributor_profile_id'] ?? 0),
            'distributor_name' => $distributorName,
            'region_id' => isset($first['region_id']) ? (int) $first['region_id'] : null,
            'region_name' => $regionName,
            'distributor_warehouse_id' => isset($first['distributor_warehouse_id'])
                ? (int) $first['distributor_warehouse_id']
                : null,
            'warehouse_name' => $warehouseName,
            'price_scope' => $priceScope,
            'price_scope_label' => $this->priceScopeLabel($priceScope),
            'sidebar_label' => $this->sidebarLabel($distributorName, $regionName, $warehouseName, $priceScope),
            'delivery_method_names' => [],
            'items' => $groupItems->values(),
            'items_count' => $groupItems->count(),
            'subtotal' => round($groupItems->sum(fn (array $row): float => (float) $row['line_total']), 2),
            'discount_amount' => round($groupItems->sum(fn (array $row): float => (float) ($row['discount_amount'] ?? 0)), 2),
            'has_blocking_warnings' => false,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $groups
     * @return Collection<int, array<string, mixed>>
     */
    private function decorateDeliveryMethods(Collection $groups): Collection
    {
        $distributorIds = $groups
            ->pluck('distributor_profile_id')
            ->filter()
            ->unique()
            ->values();

        if ($distributorIds->isEmpty()) {
            return $groups;
        }

        $distributors = DistributorProfile::query()
            ->whereIn('id', $distributorIds)
            ->with(['deliveryMethods' => function ($query): void {
                $query
                    ->where('delivery_methods.is_active', true)
                    ->wherePivot('is_active', true)
                    ->orderBy('delivery_methods.sort_order');
            }])
            ->get()
            ->keyBy('id');

        $fallbackNames = DeliveryMethod::query()
            ->active()
            ->orderBy('sort_order')
            ->pluck('name')
            ->values()
            ->all();

        return $groups->map(function (array $group) use ($distributors, $fallbackNames): array {
            $distributor = $distributors->get($group['distributor_profile_id']);
            $configured = $distributor?->deliveryMethods ?? collect();
            $group['delivery_method_names'] = $configured->isNotEmpty()
                ? $configured->pluck('name')->values()->all()
                : $fallbackNames;

            return $group;
        });
    }

    private function sidebarLabel(
        string $distributorName,
        ?string $regionName,
        ?string $warehouseName,
        string $priceScope,
    ): string {
        $parts = [$distributorName];

        if (filled($regionName)) {
            $parts[] = $regionName;
        }
        if (filled($warehouseName)) {
            $parts[] = $warehouseName;
        }
        if ($priceScope === self::PRICE_SCOPE_REGIONAL) {
            $parts[] = $this->priceScopeLabel($priceScope);
        }

        return implode(' · ', $parts);
    }
}
