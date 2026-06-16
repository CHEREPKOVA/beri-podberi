<?php

namespace App\Services\Catalog;

use App\Models\DistributorProduct;
use App\Models\DistributorProductStock;
use App\Models\DistributorProfile;
use App\Models\ManufacturerDistributorPartnership;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Цены и остатки дистрибьюторов для каталога конечной компании в регионе.
 */
class EndCompanyDistributorOfferService
{
    public function __construct(
        private readonly ?int $regionId,
    ) {}

    /**
     * ID товаров производителя, доступных к заказу в регионе (дистрибьютор + цена + опционально остаток).
     *
     * @return Builder<DistributorProduct>
     */
    public function purchasableOffersQuery(): Builder
    {
        $query = $this->regionalOffersQuery();

        if (EndCompanyCatalogSettings::requireDistributorPrice()) {
            $regionId = $this->regionId;
            $query->where(function (Builder $priceQuery) use ($regionId): void {
                $priceQuery
                    ->whereHas('regionalPrices', function (Builder $regional) use ($regionId): void {
                        $regional->where('region_id', $regionId)->where('price', '>', 0);
                    })
                    ->orWhere(function (Builder $fallback) use ($regionId): void {
                        $fallback
                            ->whereDoesntHave('regionalPrices', fn (Builder $regional) => $regional->where('region_id', $regionId))
                            ->whereNotNull('retail_price')
                            ->where('retail_price', '>', 0);
                    });
            });
        }

        if (EndCompanyCatalogSettings::requireRegionalStock()) {
            $regionId = $this->regionId;

            $query->where(function (Builder $offers) use ($regionId): void {
                // Классическое поведение: требуем положительный остаток на складе в регионе.
                $offers->whereHas('stocks', function (Builder $stockQuery) use ($regionId): void {
                    $stockQuery
                        ->whereRaw('quantity > reserved')
                        ->whereHas('warehouse', function (Builder $warehouseQuery) use ($regionId): void {
                            $warehouseQuery
                                ->where('is_active', true)
                                ->where(function (Builder $regionQuery) use ($regionId): void {
                                    $regionQuery
                                        ->whereNull('region_id')
                                        ->orWhere('region_id', $regionId);
                                });
                        });
                })
                // Новое поведение: позволяем дистрибьютору помечать нулевые остатки как «под заказ».
                ->orWhereHas('profile', function (Builder $profileQuery): void {
                    $profileQuery->where('zero_stock_behavior', DistributorProfile::ZERO_STOCK_ON_ORDER);
                });
            });
        }

        return $query;
    }

    /**
     * @return Builder<Product>
     */
    public function purchasableProductsQuery(): Builder
    {
        if ($this->regionId === null) {
            return Product::query()->whereRaw('1 = 0');
        }

        return Product::query()
            ->whereIn('id', $this->purchasableOffersQuery()->select('source_product_id'));
    }

    /**
     * Товары с дистрибьютором в регионе, но без доступной цены/остатка (для режима «недоступен»).
     *
     * @return Builder<Product>
     */
    public function unavailableInRegionProductsQuery(): Builder
    {
        if ($this->regionId === null || ! EndCompanyCatalogSettings::showUnavailableProducts()) {
            return Product::query()->whereRaw('1 = 0');
        }

        $purchasableIds = $this->purchasableOffersQuery()->select('source_product_id');

        return Product::query()
            ->whereIn('id', $this->regionalOffersQuery()->select('source_product_id'))
            ->whereNotIn('id', $purchasableIds);
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Product>
     */
    public function enrichProducts(Collection $products): Collection
    {
        if ($products->isEmpty() || $this->regionId === null) {
            return $products->map(function (Product $product): Product {
                $this->setListingAttributes($product, collect(), false);

                return $product;
            });
        }

        $regionalOffers = $this->regionalOffersQuery()
            ->whereIn('source_product_id', $products->pluck('id'))
            ->with(['stocks.warehouse.region', 'profile', 'regionalPrices'])
            ->get()
            ->groupBy('source_product_id');

        $purchasableIds = $this->filterPurchasableOfferGroups($regionalOffers)
            ->keys()
            ->flip();

        return $products->map(function (Product $product) use ($regionalOffers, $purchasableIds): Product {
            $offers = $regionalOffers->get($product->id, collect());
            $isPurchasable = $purchasableIds->has($product->id);
            $this->setListingAttributes($product, $offers, $isPurchasable);

            return $product;
        });
    }

    public function summaryForProduct(Product $product, bool $purchasableOnly = true): array
    {
        $offers = $this->offersForProduct($product->id);
        $purchasableOffers = $this->filterPurchasableOffers($offers);
        $activeOffers = $purchasableOnly ? $purchasableOffers : $offers;
        $stockRows = $this->stockRowsFromOffers($activeOffers);
        $isPurchasable = $purchasableOffers->isNotEmpty();

        return [
            'display_price' => $isPurchasable ? $this->bestRetailPriceFromOffers($purchasableOffers) : null,
            'available_stock' => $isPurchasable ? $this->totalAvailableStockFromOffers($purchasableOffers) : 0,
            'stock_rows' => $stockRows,
            'has_price' => $isPurchasable && $this->bestRetailPriceFromOffers($purchasableOffers) !== null,
            'is_purchasable' => $isPurchasable,
            'unavailable_in_region' => $offers->isNotEmpty() && ! $isPurchasable,
        ];
    }

    public function isPurchasable(Product $product): bool
    {
        if ($this->regionId === null) {
            return false;
        }

        return $this->purchasableOffersQuery()
            ->where('source_product_id', $product->id)
            ->exists();
    }

    public function hasRegionalOffer(Product $product): bool
    {
        if ($this->regionId === null) {
            return false;
        }

        return $this->regionalOffersQuery()
            ->where('source_product_id', $product->id)
            ->exists();
    }

    /**
     * @return Collection<int, Product>
     */
    public function resolveAnalogs(Product $product, Collection $linkedAnalogs): Collection
    {
        $purchasable = $linkedAnalogs->filter(fn (Product $analog): bool => $this->isPurchasable($analog))->values();

        if (! EndCompanyCatalogSettings::showUnavailableAnalogs()) {
            return $purchasable;
        }

        $unavailable = $linkedAnalogs
            ->filter(fn (Product $analog): bool => ! $this->isPurchasable($analog) && $this->hasRegionalOffer($analog))
            ->map(function (Product $analog): Product {
                $analog->setAttribute('unavailable_in_region', true);
                $analog->setAttribute('distributor_display_price', null);
                $analog->setAttribute('distributor_available_stock', 0);

                return $analog;
            });

        return $purchasable
            ->merge($unavailable)
            ->unique('id')
            ->values();
    }

    public function hasVisibleAnalogs(Product $product, Collection $linkedAnalogs): bool
    {
        if ($linkedAnalogs->isEmpty()) {
            return false;
        }

        return $this->resolveAnalogs($product, $linkedAnalogs)->isNotEmpty();
    }

    /**
     * @return Collection<int, DistributorProduct>
     */
    public function offersForProduct(int $productId): Collection
    {
        if ($this->regionId === null) {
            return collect();
        }

        return $this->regionalOffersQuery()
            ->where('source_product_id', $productId)
            ->with(['profile.regions', 'stocks.warehouse.region', 'sourceProduct', 'regionalPrices'])
            ->get()
            ->sortBy(fn (DistributorProduct $offer): float => $this->effectiveRetailPrice($offer) ?? PHP_FLOAT_MAX)
            ->values();
    }

    /**
     * @param  list<int>  $distributorProfileIds
     * @return Builder<Product>
     */
    public function applyDistributorFilter(Builder $query, array $distributorProfileIds): Builder
    {
        if ($distributorProfileIds === [] || $this->regionId === null) {
            return $query;
        }

        return $query->whereIn('products.id', $this->regionalOffersQuery()
            ->whereIn('distributor_profile_id', $distributorProfileIds)
            ->select('source_product_id'));
    }

    /**
     * @return Builder<Product>
     */
    public function applyStockFilter(Builder $query, string $stock): Builder
    {
        if ($this->regionId === null) {
            return $query->whereRaw('1 = 0');
        }

        return match ($stock) {
            CatalogListingParams::STOCK_IN_STOCK => $query->whereIn('products.id', $this->inStockProductIdsSubquery()),
            CatalogListingParams::STOCK_ON_ORDER => $query->whereIn('products.id', $this->onOrderProductIdsSubquery()),
            CatalogListingParams::STOCK_OUT_OF_STOCK => $query->whereIn('products.id', $this->unavailableInRegionProductsQuery()->select('products.id')),
            default => $query,
        };
    }

    /**
     * @return Builder<Product>
     */
    public function applyPriceFilter(Builder $query, ?float $priceMin, ?float $priceMax): Builder
    {
        if ($this->regionId === null || ($priceMin === null && $priceMax === null)) {
            return $query;
        }

        $regionId = $this->regionId;
        $offers = $this->regionalOffersQuery()->where(function (Builder $priceQuery) use ($regionId, $priceMin, $priceMax): void {
            $priceQuery
                ->whereHas('regionalPrices', function (Builder $regional) use ($regionId, $priceMin, $priceMax): void {
                    $regional->where('region_id', $regionId)->where('price', '>', 0);
                    if ($priceMin !== null) {
                        $regional->where('price', '>=', $priceMin);
                    }
                    if ($priceMax !== null) {
                        $regional->where('price', '<=', $priceMax);
                    }
                })
                ->orWhere(function (Builder $fallback) use ($regionId, $priceMin, $priceMax): void {
                    $fallback
                        ->whereDoesntHave('regionalPrices', fn (Builder $regional) => $regional->where('region_id', $regionId))
                        ->whereNotNull('retail_price')
                        ->where('retail_price', '>', 0);
                    if ($priceMin !== null) {
                        $fallback->where('retail_price', '>=', $priceMin);
                    }
                    if ($priceMax !== null) {
                        $fallback->where('retail_price', '<=', $priceMax);
                    }
                });
        });

        return $query->whereIn('products.id', $offers->select('source_product_id'));
    }

    /**
     * @return Builder<DistributorProduct>
     */
    private function inStockOffersQuery(): Builder
    {
        $regionId = $this->regionId;

        return $this->regionalOffersQuery()
            ->whereHas('stocks', function (Builder $stockQuery) use ($regionId): void {
                $stockQuery
                    ->whereRaw('quantity > reserved')
                    ->whereHas('warehouse', function (Builder $warehouseQuery) use ($regionId): void {
                        $warehouseQuery
                            ->where('is_active', true)
                            ->where(function (Builder $regionQuery) use ($regionId): void {
                                $regionQuery
                                    ->whereNull('region_id')
                                    ->orWhere('region_id', $regionId);
                            });
                    });
            });
    }

    /**
     * @return Builder<Product>
     */
    private function inStockProductIdsSubquery(): Builder
    {
        return Product::query()->select('products.id')->whereIn(
            'products.id',
            $this->inStockOffersQuery()->select('source_product_id')
        );
    }

    /**
     * @return Builder<Product>
     */
    private function onOrderProductIdsSubquery(): Builder
    {
        $inStockIds = $this->inStockOffersQuery()->select('source_product_id');

        return Product::query()
            ->select('products.id')
            ->whereIn('products.id', $this->purchasableOffersQuery()->select('source_product_id'))
            ->whereNotIn('products.id', $inStockIds);
    }

    /**
     * @return Builder<DistributorProduct>
     */
    public function regionalOffersQuery(): Builder
    {
        if ($this->regionId === null) {
            return DistributorProduct::query()->whereRaw('1 = 0');
        }

        return DistributorProduct::query()
            ->where('status', DistributorProduct::STATUS_ACTIVE)
            ->whereNotNull('source_product_id')
            ->whereHas('profile', fn ($q) => $q->inRegion($this->regionId))
            ->whereHas('profile.manufacturerPartnerships', function ($partnership): void {
                $partnership
                    ->where('status', ManufacturerDistributorPartnership::STATUS_ACTIVE)
                    ->whereColumn(
                        'manufacturer_distributor_partnerships.manufacturer_profile_id',
                        'distributor_products.manufacturer_profile_id'
                    );
            });
    }

    /**
     * @param  Collection<int, Collection<int, DistributorProduct>>  $groupedOffers
     * @return Collection<int, Collection<int, DistributorProduct>>
     */
    private function filterPurchasableOfferGroups(Collection $groupedOffers): Collection
    {
        return $groupedOffers->filter(
            fn (Collection $offers): bool => $this->filterPurchasableOffers($offers)->isNotEmpty()
        );
    }

    /**
     * @param  Collection<int, DistributorProduct>  $offers
     * @return Collection<int, DistributorProduct>
     */
    private function filterPurchasableOffers(Collection $offers): Collection
    {
        return $offers->filter(fn (DistributorProduct $offer): bool => $this->offerIsPurchasable($offer));
    }

    private function offerIsPurchasable(DistributorProduct $offer): bool
    {
        if (EndCompanyCatalogSettings::requireDistributorPrice()) {
            $price = $this->effectiveRetailPrice($offer);
            if ($price === null || $price <= 0) {
                return false;
            }
        }

        if (EndCompanyCatalogSettings::requireRegionalStock()) {
            $behavior = $offer->profile?->zeroStockBehavior() ?? DistributorProfile::ZERO_STOCK_ON_ORDER;

            // Для режима "hide" по-прежнему требуем положительный остаток.
            if ($behavior === DistributorProfile::ZERO_STOCK_HIDE
                && $this->availableStockForOffer($offer) <= 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  Collection<int, DistributorProduct>  $offers
     */
    private function setListingAttributes(Product $product, Collection $offers, bool $isPurchasable): void
    {
        $purchasableOffers = $this->filterPurchasableOffers($offers);

        $product->setAttribute('is_purchasable', $isPurchasable);
        $product->setAttribute(
            'unavailable_in_region',
            $offers->isNotEmpty() && ! $isPurchasable
        );
        $product->setAttribute(
            'distributor_display_price',
            $isPurchasable ? $this->bestRetailPriceFromOffers($purchasableOffers) : null
        );
        $product->setAttribute(
            'distributor_available_stock',
            $isPurchasable ? $this->totalAvailableStockFromOffers($purchasableOffers) : 0
        );
    }

    /**
     * @param  Collection<int, DistributorProduct>  $offers
     */
    private function bestRetailPriceFromOffers(Collection $offers): ?string
    {
        $prices = $offers
            ->map(fn (DistributorProduct $offer): ?float => $this->effectiveRetailPrice($offer))
            ->filter(static fn (?float $price): bool => $price !== null && $price > 0);

        if ($prices->isEmpty()) {
            return null;
        }

        return (string) $prices->min();
    }

    private function effectiveRetailPrice(DistributorProduct $offer): ?float
    {
        return $offer->retailPriceForRegion($this->regionId);
    }

    /**
     * @param  Collection<int, DistributorProduct>  $offers
     */
    private function totalAvailableStockFromOffers(Collection $offers): int
    {
        return (int) $offers->sum(fn (DistributorProduct $offer): int => $this->availableStockForOffer($offer));
    }

    private function availableStockForOffer(DistributorProduct $offer): int
    {
        return (int) $this->stocksInRegion($offer)->sum(
            static fn (DistributorProductStock $stock): int => $stock->available_quantity
        );
    }

    /**
     * @param  Collection<int, DistributorProduct>  $offers
     * @return Collection<int, array{
     *     distributor_name: string,
     *     warehouse_name: string,
     *     region_name: ?string,
     *     available_quantity: int,
     *     stock_updated_at: ?\Illuminate\Support\Carbon,
     *     shipping_conditions: ?string,
     *     retail_price: ?string
     * }>
     */
    private function stockRowsFromOffers(Collection $offers): Collection
    {
        $rows = collect();

        foreach ($offers as $offer) {
            foreach ($this->stocksInRegion($offer) as $stock) {
                $warehouse = $stock->warehouse;
                $rows->push([
                    'distributor_name' => $offer->profile?->displayName() ?? 'Дистрибьютор',
                    'distributor_profile_id' => $offer->distributor_profile_id,
                    'distributor_product_id' => $offer->id,
                    'warehouse_name' => $warehouse?->name ?? 'Склад',
                    'region_name' => $warehouse?->region?->name,
                    'available_quantity' => $stock->available_quantity,
                    'min_order_quantity' => $offer->min_order_quantity ?? $offer->sourceProduct?->min_order_quantity,
                    'stock_updated_at' => $stock->stock_updated_at,
                    'shipping_conditions' => $warehouse?->shipping_conditions,
                    'retail_price' => ($price = $this->effectiveRetailPrice($offer)) !== null ? (string) $price : null,
                    'status_note' => $stock->available_quantity > 0 ? null : 'Под заказ',
                ]);
            }
        }

        return $rows->sortByDesc('available_quantity')->values();
    }

    /**
     * @return Collection<int, DistributorProductStock>
     */
    private function stocksInRegion(DistributorProduct $offer): Collection
    {
        $stocks = $offer->relationLoaded('stocks')
            ? $offer->stocks
            : $offer->stocks()->with('warehouse.region')->get();

        return $stocks
            ->filter(function (DistributorProductStock $stock): bool {
                $warehouse = $stock->warehouse;
                if ($warehouse === null || ! $warehouse->is_active) {
                    return false;
                }

                if ($this->regionId === null) {
                    return false;
                }

                if ($warehouse->region_id === null) {
                    return true;
                }

                return (int) $warehouse->region_id === $this->regionId;
            })
            ->values();
    }
}
