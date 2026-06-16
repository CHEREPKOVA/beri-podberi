<?php

namespace App\Services\Catalog;

use App\Models\DistributorProduct;
use App\Models\DistributorProductStock;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Данные карточки товара в каталоге для разных ролей (п. 3.4 ТЗ).
 */
class ProductCatalogCardService
{
    private const LOGISTICS_SLUGS = [
        'weight' => 'ves-kg',
        'dimensions' => 'gabarity-dshv-mm',
        'volume' => 'obem-l',
        'pallet_qty' => 'kolichestvo-na-pallete',
        'pallet_rows' => 'ryadnost-pallet',
    ];

    public function __construct(
        private readonly User $user,
        private readonly CatalogQueryService $catalog,
    ) {}

    public function cardRole(): string
    {
        return match ($this->catalog->catalogRole()?->slug) {
            Role::SLUG_MANUFACTURER => 'manufacturer',
            Role::SLUG_DISTRIBUTOR => 'distributor',
            Role::SLUG_ADMIN, Role::SLUG_MANAGER, Role::SLUG_ANALYST => 'admin',
            default => 'end_company',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Product $product): array
    {
        $role = $this->cardRole();
        $regionName = $this->user->currentCompanyRegionName();
        $categoryAttributes = $product->attributeValuesVisibleInCategory();
        $offerService = $this->catalog->distributorOffers();

        $distributorProfileId = $role === 'distributor'
            ? $this->user->distributorProfile?->id
            : null;

        $offerSummary = $this->summaryForRole($product, $offerService, $distributorProfileId);
        $warehouseStockRows = $this->warehouseStockRowsForRole($product, $role, $offerSummary, $distributorProfileId);

        $isPurchasable = (bool) ($offerSummary['is_purchasable'] ?? false);
        $canAddToOrder = $role === 'end_company'
            && $isPurchasable
            && ! ($offerSummary['unavailable_in_region'] ?? false);

        $distributorProduct = $role === 'distributor' && $distributorProfileId
            ? DistributorProduct::query()
                ->where('source_product_id', $product->id)
                ->where('distributor_profile_id', $distributorProfileId)
                ->first()
            : null;

        $analogs = match ($role) {
            'manufacturer', 'admin' => $product->relationLoaded('analogs')
                ? $product->analogs
                : $product->analogs()->with(['images', 'category', 'attributeValues.attribute'])->get(),
            default => $this->catalog->resolveVisibleAnalogs($product),
        };

        $productUnavailable = ! $isPurchasable
            || (($offerSummary['available_stock'] ?? 0) <= 0 && EndCompanyCatalogSettings::requireRegionalStock());

        $livePayload = $this->formatLivePayload(
            $offerSummary,
            $warehouseStockRows,
            $canAddToOrder,
            $productUnavailable,
            $role,
        );

        return [
            'cardRole' => $role,
            'categoryAttributes' => $categoryAttributes,
            'offerSummary' => $offerSummary,
            'warehouseStockRows' => $warehouseStockRows,
            'logistics' => $this->logisticsParams($product),
            'analogs' => $analogs,
            'productUnavailable' => $productUnavailable,
            'canAddToOrder' => $canAddToOrder,
            'distributorProduct' => $distributorProduct,
            'companyRegionName' => $regionName,
            'companyRegionId' => $this->catalog->regionId(),
            'showAdminMeta' => in_array($role, ['manufacturer', 'admin'], true),
            'showBasePrice' => in_array($role, ['manufacturer', 'admin'], true),
            'livePayload' => $livePayload,
            'refreshSeconds' => EndCompanyCatalogSettings::productCardRefreshSeconds(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function livePayload(Product $product): array
    {
        return $this->build($product)['livePayload'];
    }

    /**
     * @param  array<string, mixed>  $offerSummary
     * @param  Collection<int, array<string, mixed>>  $warehouseStockRows
     * @return array<string, mixed>
     */
    private function formatLivePayload(
        array $offerSummary,
        Collection $warehouseStockRows,
        bool $canAddToOrder,
        bool $productUnavailable,
        string $role,
    ): array {
        $displayPrice = ($offerSummary['display_price'] ?? null) !== null
            ? (float) $offerSummary['display_price']
            : null;

        return [
            'unavailable_in_region' => (bool) ($offerSummary['unavailable_in_region'] ?? false),
            'is_purchasable' => (bool) ($offerSummary['is_purchasable'] ?? false),
            'display_price' => $displayPrice,
            'display_price_formatted' => $displayPrice !== null
                ? number_format($displayPrice, 2, ',', ' ').' ₽'
                : null,
            'available_stock' => (int) ($offerSummary['available_stock'] ?? 0),
            'can_add_to_order' => $canAddToOrder,
            'product_unavailable' => $productUnavailable,
            'show_end_company_price' => $role === 'end_company',
            'warehouse_stock_rows' => $warehouseStockRows->map(fn (array $row): array => [
                'distributor_name' => $row['distributor_name'] ?? '—',
                'warehouse_name' => $row['warehouse_name'] ?? '—',
                'region_name' => $row['region_name'] ?? '—',
                'available_quantity' => (int) ($row['available_quantity'] ?? 0),
                'min_order_quantity' => $row['min_order_quantity'] ?? '—',
                'retail_price_formatted' => ($row['retail_price'] ?? null) !== null
                    ? number_format((float) $row['retail_price'], 2, ',', ' ').' ₽'
                    : '—',
                'stock_updated_at_formatted' => isset($row['stock_updated_at']) && $row['stock_updated_at']
                    ? $row['stock_updated_at']->format('d.m.Y H:i')
                    : '—',
                'shipping_conditions' => $row['shipping_conditions'] ?: 'Стандартные',
                'status_note' => $row['status_note'] ?? null,
            ])->values()->all(),
            'refreshed_at' => now()->format('d.m.Y H:i:s'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function logisticsParams(Product $product): array
    {
        $fallback = static fn (?string $value, string $default = 'Не задан'): string => ($value !== null && $value !== '') ? $value : $default;

        return [
            'weight' => $fallback($product->attributeValueBySlug(self::LOGISTICS_SLUGS['weight']), 'Не задан'),
            'dimensions' => $fallback($product->attributeValueBySlug(self::LOGISTICS_SLUGS['dimensions']), 'Не заданы'),
            'volume' => $fallback($product->attributeValueBySlug(self::LOGISTICS_SLUGS['volume'])),
            'pallet_qty' => $fallback($product->attributeValueBySlug(self::LOGISTICS_SLUGS['pallet_qty']), 'Не заданы'),
            'pallet_rows' => $fallback($product->attributeValueBySlug(self::LOGISTICS_SLUGS['pallet_rows']), 'Не задана'),
            'packaging' => $fallback($product->storage_conditions, 'Не заданы'),
            'shipping' => $fallback($product->transport_conditions, 'Не заданы'),
            'min_order_quantity' => $product->min_order_quantity ? (string) $product->min_order_quantity : '—',
            'pack_quantity' => $product->attributeValueBySlug('kolichestvo-v-upakovke') ?: '—',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryForRole(
        Product $product,
        EndCompanyDistributorOfferService $offerService,
        ?int $distributorProfileId,
    ): array {
        if ($distributorProfileId !== null) {
            return $this->summaryForDistributorProfile($product, $offerService, $distributorProfileId);
        }

        if (in_array($this->cardRole(), ['end_company', 'distributor'], true)) {
            return $offerService->summaryForProduct($product);
        }

        return [
            'display_price' => $product->base_price,
            'available_stock' => $product->available_stock,
            'stock_rows' => collect(),
            'has_price' => $product->base_price !== null,
            'is_purchasable' => false,
            'unavailable_in_region' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryForDistributorProfile(
        Product $product,
        EndCompanyDistributorOfferService $offerService,
        int $distributorProfileId,
    ): array {
        $offers = $offerService->offersForProduct($product->id)
            ->filter(fn (DistributorProduct $offer): bool => (int) $offer->distributor_profile_id === $distributorProfileId);

        $purchasable = $offers->filter(fn (DistributorProduct $offer): bool => $this->offerIsVisible($offer));
        $stockRows = $this->buildStockRowsFromOffers($purchasable);

        $prices = $purchasable->pluck('retail_price')->filter(static fn ($p): bool => $p !== null && (float) $p > 0);

        return [
            'display_price' => $prices->isEmpty() ? null : (string) $prices->min(),
            'available_stock' => (int) $stockRows->sum('available_quantity'),
            'stock_rows' => $stockRows,
            'has_price' => $prices->isNotEmpty(),
            'is_purchasable' => $purchasable->isNotEmpty(),
            'unavailable_in_region' => $offers->isNotEmpty() && $purchasable->isEmpty(),
        ];
    }

    private function offerIsVisible(DistributorProduct $offer): bool
    {
        if (EndCompanyCatalogSettings::requireDistributorPrice()) {
            if ($offer->retail_price === null || (float) $offer->retail_price <= 0) {
                return false;
            }
        }

        if (EndCompanyCatalogSettings::requireRegionalStock()) {
            $stock = (int) $offer->stocks->sum(fn (DistributorProductStock $s) => $s->available_quantity);

            return $stock > 0;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $offerSummary
     * @return Collection<int, array<string, mixed>>
     */
    private function warehouseStockRowsForRole(
        Product $product,
        string $role,
        array $offerSummary,
        ?int $distributorProfileId,
    ): Collection {
        if (in_array($role, ['end_company', 'distributor'], true)) {
            $rows = collect($offerSummary['stock_rows'] ?? []);

            if ($distributorProfileId !== null) {
                $rows = $rows->filter(
                    fn (array $row): bool => (int) ($row['distributor_profile_id'] ?? 0) === $distributorProfileId
                );
            }

            return $rows->values();
        }

        $manufacturerRows = $product->stocks
            ->loadMissing('warehouse.region')
            ->sortByDesc('stock_updated_at')
            ->map(fn (ProductStock $stock): array => [
                'distributor_name' => $product->manufacturerProfile?->displayName() ?? 'Производитель',
                'distributor_profile_id' => null,
                'distributor_product_id' => null,
                'warehouse_name' => $stock->warehouse?->name ?? 'Склад',
                'region_name' => $stock->warehouse?->region?->name,
                'available_quantity' => $stock->available_quantity,
                'min_order_quantity' => $product->min_order_quantity,
                'stock_updated_at' => $stock->stock_updated_at,
                'shipping_conditions' => $stock->warehouse?->shipping_conditions,
                'retail_price' => $product->base_price !== null ? (string) $product->base_price : null,
                'status_note' => null,
            ]);

        $distributorRows = DistributorProduct::query()
            ->where('source_product_id', $product->id)
            ->where('status', DistributorProduct::STATUS_ACTIVE)
            ->with(['profile', 'stocks.warehouse.region', 'sourceProduct'])
            ->get()
            ->flatMap(function (DistributorProduct $offer): Collection {
                return $offer->stocks->map(fn (DistributorProductStock $stock): array => [
                    'distributor_name' => $offer->profile?->displayName() ?? 'Дистрибьютор',
                    'distributor_profile_id' => $offer->distributor_profile_id,
                    'distributor_product_id' => $offer->id,
                    'warehouse_name' => $stock->warehouse?->name ?? 'Склад',
                    'region_name' => $stock->warehouse?->region?->name,
                    'available_quantity' => $stock->available_quantity,
                    'min_order_quantity' => $offer->min_order_quantity ?? $offer->sourceProduct?->min_order_quantity,
                    'stock_updated_at' => $stock->stock_updated_at,
                    'shipping_conditions' => $stock->warehouse?->shipping_conditions,
                    'retail_price' => $offer->retail_price !== null ? (string) $offer->retail_price : null,
                    'status_note' => $stock->available_quantity > 0 ? null : 'Под заказ',
                ]);
            });

        return $manufacturerRows->concat($distributorRows)->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildStockRowsFromOffers(Collection $offers): Collection
    {
        $regionId = $this->catalog->regionId();
        $rows = collect();

        foreach ($offers as $offer) {
            $stocks = $offer->relationLoaded('stocks')
                ? $offer->stocks
                : $offer->stocks()->with('warehouse.region')->get();

            foreach ($stocks as $stock) {
                $warehouse = $stock->warehouse;
                if ($warehouse === null || ! $warehouse->is_active) {
                    continue;
                }
                if ($regionId !== null && $warehouse->region_id !== null && (int) $warehouse->region_id !== $regionId) {
                    continue;
                }

                $rows->push([
                    'distributor_name' => $offer->profile?->displayName() ?? 'Дистрибьютор',
                    'distributor_profile_id' => $offer->distributor_profile_id,
                    'distributor_product_id' => $offer->id,
                    'warehouse_name' => $warehouse->name ?? 'Склад',
                    'region_name' => $warehouse->region?->name,
                    'available_quantity' => $stock->available_quantity,
                    'min_order_quantity' => $offer->min_order_quantity ?? $offer->sourceProduct?->min_order_quantity,
                    'stock_updated_at' => $stock->stock_updated_at,
                    'shipping_conditions' => $warehouse->shipping_conditions,
                    'retail_price' => $offer->retail_price !== null ? (string) $offer->retail_price : null,
                    'status_note' => $stock->available_quantity > 0 ? null : 'Под заказ',
                ]);
            }
        }

        return $rows->sortByDesc('available_quantity')->values();
    }
}
