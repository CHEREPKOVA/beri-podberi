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
        $warehouseStockRows = $this->warehouseStockRowsForRole($product, $role, $offerSummary);

        $isPurchasable = (bool) ($offerSummary['is_purchasable'] ?? false);
        $canAddToOrder = $role === 'end_company'
            && $isPurchasable
            && ! ($offerSummary['unavailable_in_region'] ?? false);

        $canAddToPurchase = false;
        $purchasePrice = null;
        $purchaseStock = 0;
        $purchaseMinQty = 1;
        if ($role === 'distributor' && $product->manufacturer_profile_id) {
            $partnerIds = $this->catalog->activePartnerManufacturerIds();
            if (in_array((int) $product->manufacturer_profile_id, $partnerIds, true)) {
                $regionId = $this->catalog->regionId();
                $purchasePrice = (float) $product->getPriceForRegion($regionId);
                $purchaseStock = $product->getAvailableStockInRegion($regionId);
                $purchaseMinQty = max(1, (int) ($product->min_order_quantity ?? 1));
                $canAddToPurchase = $purchasePrice > 0;
            }
        }

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

        $productUnavailable = $role === 'distributor'
            ? ! $canAddToPurchase
            : (
                ! $isPurchasable
                || (($offerSummary['available_stock'] ?? 0) <= 0 && EndCompanyCatalogSettings::requireRegionalStock())
            );

        $livePayload = $this->formatLivePayload(
            $offerSummary,
            $warehouseStockRows,
            $canAddToOrder,
            $canAddToPurchase,
            $purchasePrice,
            $purchaseStock,
            $purchaseMinQty,
            $productUnavailable,
            $role,
            $product,
            $offerService,
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
            'canAddToPurchase' => $canAddToPurchase,
            'purchasePrice' => $purchasePrice,
            'purchaseStock' => $purchaseStock,
            'purchaseMinQty' => $purchaseMinQty,
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
        bool $canAddToPurchase,
        ?float $purchasePrice,
        int $purchaseStock,
        int $purchaseMinQty,
        bool $productUnavailable,
        string $role,
        Product $product,
        EndCompanyDistributorOfferService $offerService,
    ): array {
        $displayPrice = $role === 'distributor' && $purchasePrice !== null
            ? $purchasePrice
            : (($offerSummary['display_price'] ?? null) !== null
                ? (float) $offerSummary['display_price']
                : null);

        $cartOffers = $role === 'end_company'
            ? $this->cartOffersForProduct($product, $offerService)
            : [];

        return [
            'unavailable_in_region' => (bool) ($offerSummary['unavailable_in_region'] ?? false),
            'is_purchasable' => (bool) ($offerSummary['is_purchasable'] ?? false),
            'display_price' => $displayPrice,
            'display_price_formatted' => $displayPrice !== null
                ? number_format($displayPrice, 2, ',', ' ').' ₽'
                : null,
            'available_stock' => $role === 'distributor' ? $purchaseStock : (int) ($offerSummary['available_stock'] ?? 0),
            'can_add_to_order' => $canAddToOrder,
            'can_add_to_purchase' => $canAddToPurchase,
            'purchase_min_qty' => $purchaseMinQty,
            'product_unavailable' => $productUnavailable,
            'show_end_company_price' => $role === 'end_company',
            'show_purchase_price' => $role === 'distributor' && $canAddToPurchase,
            'cart_offers' => $cartOffers,
            'selected_distributor_product_id' => count($cartOffers) === 1
                ? $cartOffers[0]['distributor_product_id']
                : null,
            'warehouse_stock_rows' => $warehouseStockRows->map(function (array $row): array {
                $rawMin = $row['min_order_quantity'] ?? null;
                $minQty = max(1, (int) ($rawMin ?? 1));

                return [
                    'product_id' => $row['product_id'] ?? null,
                    'distributor_product_id' => $row['distributor_product_id'] ?? null,
                    'distributor_name' => $row['distributor_name'] ?? '—',
                    'warehouse_name' => $row['warehouse_name'] ?? '—',
                    'region_name' => $row['region_name'] ?? '—',
                    'available_quantity' => (int) ($row['available_quantity'] ?? 0),
                    'min_order_quantity' => $minQty,
                    'min_order_quantity_label' => $rawMin !== null && $rawMin !== ''
                        ? (string) (int) $rawMin
                        : '—',
                    'retail_price_formatted' => ($row['retail_price'] ?? null) !== null
                        ? number_format((float) $row['retail_price'], 2, ',', ' ').' ₽'
                        : '—',
                    'stock_updated_at_formatted' => isset($row['stock_updated_at']) && $row['stock_updated_at']
                        ? $row['stock_updated_at']->format('d.m.Y H:i')
                        : '—',
                    'shipping_conditions' => $row['shipping_conditions'] ?: 'Стандартные',
                    'status_note' => $row['status_note'] ?? null,
                ];
            })->values()->all(),
            'refreshed_at' => now()->format('d.m.Y H:i:s'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cartOffersForProduct(Product $product, EndCompanyDistributorOfferService $offerService): array
    {
        return $offerService->offersForProduct($product->id)
            ->filter(fn (DistributorProduct $offer): bool => $offerService->isOfferPurchasable($offer))
            ->map(function (DistributorProduct $offer) use ($offerService): array {
                $price = $offerService->effectiveRetailPrice($offer);
                $minQty = (int) ($offer->min_order_quantity ?? $offer->sourceProduct?->min_order_quantity ?? 1);

                return [
                    'distributor_product_id' => $offer->id,
                    'distributor_profile_id' => $offer->distributor_profile_id,
                    'distributor_name' => $offer->profile?->displayName() ?? 'Поставщик',
                    'available_stock' => $offerService->availableStockForOffer($offer),
                    'min_order_quantity' => max(1, $minQty),
                    'retail_price' => $price,
                    'retail_price_formatted' => $price !== null
                        ? number_format($price, 2, ',', ' ').' ₽'
                        : '—',
                ];
            })
            ->values()
            ->all();
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
     * Склады производителя, у которого дистрибьютор закупает товар.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function manufacturerPurchaseStockRows(Product $product): Collection
    {
        $regionId = $this->catalog->regionId();
        $product->loadMissing(['stocks.warehouse.region', 'manufacturerProfile']);
        $price = (float) $product->getPriceForRegion($regionId);
        $priceString = $price > 0 ? (string) $price : null;
        $supplierName = $product->manufacturerProfile?->displayName() ?: 'Производитель';

        $rows = $product->visibleStocksForRegion($regionId)->map(
            fn (ProductStock $stock): array => [
                'distributor_name' => $supplierName,
                'distributor_profile_id' => null,
                'distributor_product_id' => null,
                'product_id' => $product->id,
                'warehouse_name' => $stock->warehouse?->name ?? 'Склад',
                'region_name' => $stock->warehouse?->region?->name,
                'available_quantity' => $stock->available_quantity,
                'min_order_quantity' => $product->min_order_quantity,
                'stock_updated_at' => $stock->stock_updated_at,
                'shipping_conditions' => $stock->warehouse?->shipping_conditions,
                'retail_price' => $priceString,
                'status_note' => $stock->available_quantity > 0 ? null : 'Под заказ',
            ]
        );

        if ($rows->isNotEmpty()) {
            return $rows->values();
        }

        return collect([[
            'distributor_name' => $supplierName,
            'distributor_profile_id' => null,
            'distributor_product_id' => null,
            'product_id' => $product->id,
            'warehouse_name' => '—',
            'region_name' => $this->user->currentCompanyRegionName(),
            'available_quantity' => $product->getAvailableStockInRegion($regionId),
            'min_order_quantity' => $product->min_order_quantity,
            'stock_updated_at' => null,
            'shipping_conditions' => null,
            'retail_price' => $priceString,
            'status_note' => 'Под заказ',
        ]]);
    }

    /**
     * @param  array<string, mixed>  $offerSummary
     * @return Collection<int, array<string, mixed>>
     */
    private function warehouseStockRowsForRole(
        Product $product,
        string $role,
        array $offerSummary,
    ): Collection {
        if ($role === 'distributor') {
            return $this->manufacturerPurchaseStockRows($product);
        }

        if ($role === 'end_company') {
            return collect($offerSummary['stock_rows'] ?? [])->values();
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
