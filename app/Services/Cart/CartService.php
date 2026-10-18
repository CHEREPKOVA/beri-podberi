<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DistributorProduct;
use App\Models\DistributorProductPriceHistory;
use App\Models\DistributorProfile;
use App\Models\EndCompanyProfile;
use App\Models\PlatformOrder;
use App\Models\User;
use App\Services\Catalog\CatalogRegionService;
use App\Services\Catalog\EndCompanyCatalogSettings;
use App\Services\Catalog\EndCompanyDistributorOfferService;
use App\Services\Discount\DistributorDiscountEngine;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CartService
{
    public const WARNING_OUT_OF_STOCK = 'out_of_stock';

    public const WARNING_PRICE_CHANGED = 'price_changed';

    public const WARNING_MIN_QUANTITY = 'min_quantity';

    public const WARNING_PACK_MULTIPLICITY = 'pack_multiplicity';

    public const WARNING_SUPPLIER_UNAVAILABLE = 'supplier_unavailable';

    public const WARNING_REGION = 'region';

    public function __construct(
        private readonly CatalogRegionService $regionService,
        private readonly CartSplitService $splitService,
        private readonly DistributorDiscountEngine $discountEngine,
    ) {}

    public function getOrCreateCart(EndCompanyProfile $profile): Cart
    {
        return Cart::query()->firstOrCreate([
            'end_company_profile_id' => $profile->id,
        ]);
    }

    public function resolveProfile(User $user): EndCompanyProfile
    {
        return $user->currentEndCompanyProfile() ?? $user->getOrCreateEndCompanyProfile();
    }

    /**
     * @return array{
     *     cart: Cart,
     *     groups: Collection<int, array<string, mixed>>,
     *     blocked_items: Collection<int, array<string, mixed>>,
     *     totals: array{groups_count: int, items_count: int, blocked_items_count: int, total_amount: float, discount_amount: float},
     *     has_blocking_warnings: bool
     * }
     */
    public function view(User $user): array
    {
        $profile = $this->resolveProfile($user);
        $cart = $this->getOrCreateCart($profile);
        $regionId = $this->regionService->resolveRegionId($user);
        $offerService = new EndCompanyDistributorOfferService($regionId);

        $items = $cart->items()
            ->with([
                'region',
                'warehouse',
                'distributorProduct.profile',
                'distributorProduct.manufacturerProfile',
                'distributorProduct.sourceProduct.manufacturerProfile',
                'distributorProduct.stocks.warehouse.region',
                'distributorProduct.regionalPrices',
                'distributorProduct.priceHistories',
            ])
            ->orderBy('id')
            ->get();

        $items->each(fn (CartItem $item) => $this->backfillFulfillmentSnapshot($item, $offerService, $regionId));

        $enriched = $items->map(fn (CartItem $item): array => $this->enrichItem($item, $offerService, $regionId));
        $enriched = $this->applyDistributorDiscounts($profile, $enriched);
        $split = $this->splitService->build($enriched);
        $groups = $split['groups'];
        $blockedItems = $split['blocked_items'];

        return [
            'cart' => $cart,
            'groups' => $groups,
            'blocked_items' => $blockedItems,
            'totals' => [
                'groups_count' => $groups->count(),
                'items_count' => $enriched->count(),
                'blocked_items_count' => $blockedItems->count(),
                'total_amount' => round($enriched->sum(fn (array $row): float => (float) $row['line_total']), 2),
                'discount_amount' => round($enriched->sum(fn (array $row): float => (float) ($row['discount_amount'] ?? 0)), 2),
            ],
            'has_blocking_warnings' => $blockedItems->isNotEmpty(),
        ];
    }

    public function itemsCount(User $user): int
    {
        $profile = $user->currentEndCompanyProfile();
        if ($profile === null) {
            return 0;
        }

        $cart = $profile->cart;

        return $cart ? (int) $cart->items()->count() : 0;
    }

    public function add(User $user, int $distributorProductId, int $quantity = 1): CartItem
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Количество должно быть не меньше 1.',
            ]);
        }

        $profile = $this->resolveProfile($user);
        $cart = $this->getOrCreateCart($profile);
        $regionId = $this->regionService->resolveRegionId($user);
        $offerService = new EndCompanyDistributorOfferService($regionId);

        $offer = $this->loadOffer($distributorProductId);
        $this->assertOfferInCartRegion($offer, $offerService);

        $price = $offerService->effectiveRetailPrice($offer);
        if ($price === null || $price <= 0) {
            throw ValidationException::withMessages([
                'distributor_product_id' => 'У выбранного поставщика нет актуальной цены.',
            ]);
        }

        $listPrice = $this->resolveListUnitPrice($offer, $offerService);

        $existing = $this->findExistingItem($cart, $offer->id, $regionId);

        $resultingQuantity = $existing
            ? ((int) $existing->quantity + $quantity)
            : $quantity;

        $this->assertQuantityConstraints($offer, $resultingQuantity);

        $warehouseId = $offerService->resolveFulfillmentWarehouseId(
            $offer,
            $resultingQuantity,
            $existing?->distributor_warehouse_id !== null ? (int) $existing->distributor_warehouse_id : null,
        );

        if ($existing) {
            $existing->quantity = $resultingQuantity;
            $existing->unit_price = $price;
            $existing->list_unit_price = $listPrice;
            $existing->region_id = $regionId;
            $existing->distributor_warehouse_id = $warehouseId;
            $existing->save();

            return $existing->fresh(['distributorProduct', 'region', 'warehouse']);
        }

        return $cart->items()->create([
            'distributor_product_id' => $offer->id,
            'region_id' => $regionId,
            'distributor_warehouse_id' => $warehouseId,
            'quantity' => $quantity,
            'unit_price' => $price,
            'list_unit_price' => $listPrice,
        ]);
    }

    /**
     * Переносит позиции заказа в корзину с исходными количествами.
     *
     * @return array{
     *     added: int,
     *     skipped: list<string>,
     *     distributor_profile_id: ?int,
     *     distributor_name: ?string
     * }
     */
    public function repeatFromOrder(User $user, PlatformOrder $order): array
    {
        $order->loadMissing(['items', 'distributorProfile']);

        $added = 0;
        $skipped = [];

        foreach ($order->items as $item) {
            $offerId = $item->distributor_product_id;
            if ($offerId === null) {
                $skipped[] = $item->name.' (товар больше недоступен)';

                continue;
            }

            try {
                $this->add($user, (int) $offerId, max(1, (int) $item->quantity));
                $added++;
            } catch (ValidationException $e) {
                $message = collect($e->errors())->flatten()->first() ?: 'не удалось добавить';
                $skipped[] = $item->name.' ('.$message.')';
            }
        }

        if ($added === 0) {
            throw ValidationException::withMessages([
                'order' => $skipped !== []
                    ? 'Не удалось перенести позиции в корзину: '.implode('; ', $skipped)
                    : 'В заказе нет позиций для повтора.',
            ]);
        }

        return [
            'added' => $added,
            'skipped' => $skipped,
            'distributor_profile_id' => $order->distributor_profile_id,
            'distributor_name' => $order->distributorProfile?->displayName(),
        ];
    }

    public function updateQuantity(User $user, CartItem $item, int $quantity): CartItem
    {
        $this->assertOwnsItem($user, $item);

        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Количество должно быть не меньше 1.',
            ]);
        }

        $catalogRegionId = $this->regionService->resolveRegionId($user);
        $regionId = $item->region_id !== null ? (int) $item->region_id : $catalogRegionId;
        $offerService = new EndCompanyDistributorOfferService($regionId);
        $offer = $this->loadOffer($item->distributor_product_id);
        $this->assertQuantityConstraints($offer, $quantity);
        $price = $offerService->effectiveRetailPrice($offer);
        $listPrice = $this->resolveListUnitPrice($offer, $offerService);
        $warehouseId = $offerService->resolveFulfillmentWarehouseId(
            $offer,
            $quantity,
            $item->distributor_warehouse_id !== null ? (int) $item->distributor_warehouse_id : null,
        );

        $item->quantity = $quantity;
        if ($price !== null && $price > 0) {
            $item->unit_price = $price;
        }
        $item->list_unit_price = $listPrice;
        if ($item->region_id === null && $regionId !== null) {
            $item->region_id = $regionId;
        }
        $item->distributor_warehouse_id = $warehouseId;
        $item->save();

        return $item->fresh(['distributorProduct', 'region', 'warehouse']);
    }

    public function remove(User $user, CartItem $item): void
    {
        $this->assertOwnsItem($user, $item);
        $item->delete();
    }

    /**
     * @return array{
     *     groups: Collection<int, array<string, mixed>>,
     *     blocked_items: Collection<int, array<string, mixed>>,
     *     totals: array{groups_count: int, items_count: int, blocked_items_count: int, total_amount: float, discount_amount: float},
     *     has_blocking_warnings: bool,
     *     refreshed_at: string
     * }
     */
    public function live(User $user): array
    {
        $view = $this->view($user);

        return [
            'groups' => $view['groups'],
            'blocked_items' => $view['blocked_items'],
            'totals' => $view['totals'],
            'has_blocking_warnings' => $view['has_blocking_warnings'],
            'refreshed_at' => now()->format('d.m.Y H:i:s'),
        ];
    }

    /**
     * @param  list<int|string>  $cartItemIds
     */
    public function clearItems(User $user, array $cartItemIds): void
    {
        $ids = collect($cartItemIds)
            ->filter()
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $profile = $this->resolveProfile($user);
        $cart = $this->getOrCreateCart($profile);

        $cart->items()->whereIn('id', $ids)->delete();
    }

    public function clearGroup(User $user, int $distributorProfileId): void
    {
        $profile = $this->resolveProfile($user);
        $cart = $this->getOrCreateCart($profile);

        $cart->items()
            ->whereHas('distributorProduct', fn ($q) => $q->where('distributor_profile_id', $distributorProfileId))
            ->delete();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $enriched
     * @return Collection<int, array<string, mixed>>
     */
    private function applyDistributorDiscounts(EndCompanyProfile $company, Collection $enriched): Collection
    {
        $applied = collect();

        foreach ($enriched->groupBy(fn (array $row): int => (int) ($row['distributor_profile_id'] ?? 0)) as $distributorId => $rows) {
            if ((int) $distributorId <= 0) {
                $applied = $applied->concat($rows);

                continue;
            }

            $payload = $rows->values()->map(fn (array $row): array => [
                'distributor_product_id' => (int) ($row['distributor_product_id'] ?? 0),
                'category_id' => isset($row['product_category_id']) ? (int) $row['product_category_id'] : null,
                'brand' => $row['brand'] ?? null,
                'quantity' => (int) $row['quantity'],
                'list_unit_price' => (float) $row['unit_price'],
                'warehouse_id' => $row['distributor_warehouse_id'] ?? null,
                'status' => DistributorProduct::STATUS_ACTIVE,
            ])->all();

            $quoted = $this->discountEngine->quote(
                (int) $distributorId,
                $company,
                $payload,
                isset($rows->first()['region_id']) ? (int) $rows->first()['region_id'] : null,
            );

            $applied = $applied->concat($rows->values()->map(function (array $row, int $index) use ($quoted): array {
                $quote = $quoted['items'][$index] ?? null;
                if ($quote === null || empty($quote['has_discount'])) {
                    return $row;
                }

                $unit = (float) $quote['unit_price'];
                $previous = (float) $row['unit_price'];
                if ($unit >= $previous - 0.009) {
                    return $row;
                }

                $list = (float) ($row['list_unit_price'] ?? $previous);
                if ($list <= $unit + 0.009) {
                    $list = $previous;
                }
                $qty = (int) $row['quantity'];

                $row['unit_price'] = $unit;
                $row['list_unit_price'] = $list;
                $row['has_discount'] = true;
                $row['discount_amount'] = round(($list - $unit) * $qty, 2);
                $row['line_total'] = round($unit * $qty, 2);
                $row['unit_price_formatted'] = number_format($unit, 2, ',', ' ').' ₽';
                $row['list_unit_price_formatted'] = number_format($list, 2, ',', ' ').' ₽';
                $row['line_total_formatted'] = number_format($row['line_total'], 2, ',', ' ').' ₽';
                $row['discount_set_id'] = $quote['discount_set_id'] ?? null;
                $row['distributor_discount_set_id'] = $quote['discount_set_id'] ?? null;
                $row['discount_label'] = $quote['discount_label'] ?? null;

                return $row;
            }));
        }

        return $applied->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function enrichItem(
        CartItem $item,
        EndCompanyDistributorOfferService $offerService,
        ?int $regionId,
    ): array {
        $offer = $item->distributorProduct;
        $itemRegionId = $item->region_id !== null ? (int) $item->region_id : $regionId;
        $itemOfferService = $itemRegionId === $regionId
            ? $offerService
            : new EndCompanyDistributorOfferService($itemRegionId);
        $warnings = $this->buildWarnings($item, $offer, $itemOfferService, $itemRegionId);
        $currentPrice = $offer ? $itemOfferService->effectiveRetailPrice($offer) : null;
        $displayPrice = $currentPrice ?? (float) $item->unit_price;
        $listPrice = $this->resolveListUnitPriceFromStored($item, $offer, $itemOfferService, $displayPrice);
        $hasDiscount = $listPrice !== null && $listPrice > $displayPrice + 0.009;
        $discountAmount = $hasDiscount
            ? round(($listPrice - $displayPrice) * $item->quantity, 2)
            : 0.0;
        $availableStock = $offer ? $itemOfferService->availableStockForOffer($offer) : 0;
        $priceScope = $this->splitService->resolvePriceScope($offer, $itemRegionId);

        return [
            'id' => $item->id,
            'distributor_product_id' => $item->distributor_product_id,
            'region_id' => $item->region_id !== null ? (int) $item->region_id : $itemRegionId,
            'region_name' => $item->region?->name,
            'distributor_warehouse_id' => $item->distributor_warehouse_id !== null
                ? (int) $item->distributor_warehouse_id
                : null,
            'warehouse_name' => $item->warehouse?->name,
            'price_scope' => $priceScope,
            'price_scope_label' => $this->splitService->priceScopeLabel($priceScope),
            'distributor_profile_id' => (int) ($offer?->distributor_profile_id ?? 0),
            'distributor_name' => $offer?->profile?->displayName() ?? 'Поставщик',
            'manufacturer_profile_id' => $offer?->manufacturer_profile_id,
            'product_category_id' => $offer?->product_category_id ? (int) $offer->product_category_id : null,
            'brand' => $offer?->brand,
            'product_id' => $offer?->source_product_id,
            'discount_set_id' => null,
            'distributor_discount_set_id' => null,
            'discount_label' => null,
            'name' => $offer?->name ?? 'Товар недоступен',
            'sku' => $offer?->manufacturer_sku ?: $offer?->internal_sku ?: $offer?->sourceProduct?->sku,
            'manufacturer_name' => $offer?->manufacturerName() ?? '—',
            'quantity' => $item->quantity,
            'unit_price' => $displayPrice,
            'list_unit_price' => $hasDiscount ? $listPrice : null,
            'has_discount' => $hasDiscount,
            'discount_amount' => $discountAmount,
            'unit_price_formatted' => number_format($displayPrice, 2, ',', ' ').' ₽',
            'list_unit_price_formatted' => $hasDiscount
                ? number_format($listPrice, 2, ',', ' ').' ₽'
                : null,
            'cart_unit_price' => $item->unit_price !== null ? (float) $item->unit_price : null,
            'line_total' => round($displayPrice * $item->quantity, 2),
            'line_total_formatted' => number_format(round($displayPrice * $item->quantity, 2), 2, ',', ' ').' ₽',
            'available_stock' => $availableStock,
            'min_order_quantity' => $this->minOrderQuantity($offer),
            'pack_quantity' => $offer?->pack_quantity,
            'warnings' => $warnings,
            'product_url' => $offer?->source_product_id
                ? route('buyer.catalog.show', $offer->source_product_id)
                : null,
        ];
    }

    /**
     * @return list<array{code: string, message: string, blocking: bool}>
     */
    private function buildWarnings(
        CartItem $item,
        ?DistributorProduct $offer,
        EndCompanyDistributorOfferService $offerService,
        ?int $regionId,
    ): array {
        $warnings = [];

        if ($offer === null || $offer->trashed() || $offer->status !== DistributorProduct::STATUS_ACTIVE) {
            $warnings[] = [
                'code' => self::WARNING_SUPPLIER_UNAVAILABLE,
                'message' => 'Поставщик временно недоступен или товар снят с продажи.',
                'blocking' => true,
            ];

            return $warnings;
        }

        $inRegion = $offerService->regionalOffersQuery()
            ->whereKey($offer->id)
            ->exists();

        if (! $inRegion) {
            $warnings[] = [
                'code' => self::WARNING_REGION,
                'message' => 'Поставщик не работает в выбранном регионе.',
                'blocking' => true,
            ];
        }

        if (! $offerService->isOfferPurchasable($offer)) {
            $warnings[] = [
                'code' => self::WARNING_SUPPLIER_UNAVAILABLE,
                'message' => 'Оффер поставщика недоступен для заказа.',
                'blocking' => true,
            ];
        }

        $available = $offerService->availableStockForOffer($offer);
        if ($available <= 0) {
            $zeroBehavior = $offer->profile?->zeroStockBehavior() ?? DistributorProfile::ZERO_STOCK_ON_ORDER;
            $blocking = EndCompanyCatalogSettings::requireRegionalStock()
                || $zeroBehavior === DistributorProfile::ZERO_STOCK_HIDE;

            $warnings[] = [
                'code' => self::WARNING_OUT_OF_STOCK,
                'message' => $blocking
                    ? 'Товар исчез из наличия у поставщика.'
                    : 'Товар отсутствует на складе — доступен под заказ.',
                'blocking' => $blocking,
            ];
        } elseif ($item->quantity > $available) {
            $warnings[] = [
                'code' => self::WARNING_OUT_OF_STOCK,
                'message' => "Доступно только {$available} шт.",
                'blocking' => true,
            ];
        }

        $currentPrice = $offerService->effectiveRetailPrice($offer);
        if (
            $item->unit_price !== null
            && $currentPrice !== null
            && abs((float) $item->unit_price - $currentPrice) > 0.009
        ) {
            $old = number_format((float) $item->unit_price, 2, ',', ' ');
            $new = number_format($currentPrice, 2, ',', ' ');
            $warnings[] = [
                'code' => self::WARNING_PRICE_CHANGED,
                'message' => "Цена изменилась: было {$old} ₽, стало {$new} ₽.",
                'blocking' => false,
            ];
        }

        $minQty = $this->minOrderQuantity($offer);
        if ($minQty !== null && $item->quantity < $minQty) {
            $warnings[] = [
                'code' => self::WARNING_MIN_QUANTITY,
                'message' => "Минимальная партия — {$minQty} шт.",
                'blocking' => true,
            ];
        }

        $packQty = $offer->pack_quantity;
        if ($packQty !== null && (int) $packQty > 1 && $item->quantity % (int) $packQty !== 0) {
            $warnings[] = [
                'code' => self::WARNING_PACK_MULTIPLICITY,
                'message' => "Количество должно быть кратно упаковке ({$packQty} шт.).",
                'blocking' => true,
            ];
        }

        return $warnings;
    }

    private function minOrderQuantity(?DistributorProduct $offer): ?int
    {
        if ($offer === null) {
            return null;
        }

        $value = $offer->min_order_quantity ?? $offer->sourceProduct?->min_order_quantity;

        return $value !== null ? (int) $value : null;
    }

    private function assertQuantityConstraints(DistributorProduct $offer, int $quantity): void
    {
        $minQty = $this->minOrderQuantity($offer);
        if ($minQty !== null && $minQty > 1 && $quantity < $minQty) {
            throw ValidationException::withMessages([
                'quantity' => "Минимальная партия — {$minQty} шт. Укажите количество не меньше {$minQty}.",
            ]);
        }

        $packQty = $offer->pack_quantity;
        if ($packQty !== null && (int) $packQty > 1 && $quantity % (int) $packQty !== 0) {
            throw ValidationException::withMessages([
                'quantity' => "Количество должно быть кратно упаковке ({$packQty} шт.).",
            ]);
        }
    }

    private function loadOffer(int $distributorProductId): DistributorProduct
    {
        $offer = DistributorProduct::query()
            ->with(['profile', 'stocks.warehouse', 'regionalPrices', 'sourceProduct', 'priceHistories'])
            ->find($distributorProductId);

        if ($offer === null) {
            throw ValidationException::withMessages([
                'distributor_product_id' => 'Оффер поставщика не найден.',
            ]);
        }

        return $offer;
    }

    private function assertOfferInCartRegion(
        DistributorProduct $offer,
        EndCompanyDistributorOfferService $offerService,
    ): void {
        $exists = $offerService->regionalOffersQuery()
            ->whereKey($offer->id)
            ->exists();

        if (! $exists || ! $offerService->isOfferPurchasable($offer)) {
            throw ValidationException::withMessages([
                'distributor_product_id' => 'Выбранный поставщик недоступен в вашем регионе.',
            ]);
        }
    }

    private function findExistingItem(Cart $cart, int $distributorProductId, ?int $regionId): ?CartItem
    {
        $query = $cart->items()->where('distributor_product_id', $distributorProductId);

        if ($regionId !== null) {
            $query->where(function ($inner) use ($regionId): void {
                $inner->where('region_id', $regionId)->orWhereNull('region_id');
            });
        }

        return $query
            ->orderByRaw('case when region_id is null then 1 else 0 end')
            ->first();
    }

    /**
     * Для старых позиций без региона/склада дописывает снимок, не пересчитывая уже зафиксированные значения.
     */
    private function backfillFulfillmentSnapshot(
        CartItem $item,
        EndCompanyDistributorOfferService $catalogOfferService,
        ?int $catalogRegionId,
    ): void {
        $dirty = false;

        if ($item->region_id === null && $catalogRegionId !== null) {
            $item->region_id = $catalogRegionId;
            $dirty = true;
        }

        if ($item->distributor_warehouse_id !== null) {
            if ($dirty) {
                $item->save();
            }

            return;
        }

        $regionId = $item->region_id !== null ? (int) $item->region_id : $catalogRegionId;
        $offer = $item->distributorProduct;
        if ($offer === null || $regionId === null) {
            if ($dirty) {
                $item->save();
            }

            return;
        }

        $offerService = $regionId === $catalogRegionId
            ? $catalogOfferService
            : new EndCompanyDistributorOfferService($regionId);

        $warehouseId = $offerService->resolveFulfillmentWarehouseId($offer, (int) $item->quantity);
        if ($warehouseId !== null) {
            $item->distributor_warehouse_id = $warehouseId;
            $dirty = true;
        }

        if ($dirty) {
            $item->save();
        }
    }

    private function assertOwnsItem(User $user, CartItem $item): void
    {
        $profile = $this->resolveProfile($user);
        $item->loadMissing('cart');

        if ((int) $item->cart?->end_company_profile_id !== (int) $profile->id) {
            abort(403);
        }
    }

    private function resolveListUnitPrice(
        DistributorProduct $offer,
        EndCompanyDistributorOfferService $offerService,
    ): ?float {
        $current = $offerService->effectiveRetailPrice($offer);
        if ($current === null || $current <= 0) {
            return null;
        }

        $list = $this->detectListUnitPrice($offer, $current);

        return $list !== null && $list > $current + 0.009 ? $list : null;
    }

    private function resolveListUnitPriceFromStored(
        CartItem $item,
        ?DistributorProduct $offer,
        EndCompanyDistributorOfferService $offerService,
        float $displayPrice,
    ): ?float {
        if ($item->list_unit_price !== null && (float) $item->list_unit_price > $displayPrice + 0.009) {
            return (float) $item->list_unit_price;
        }

        if ($offer === null) {
            return null;
        }

        return $this->resolveListUnitPrice($offer, $offerService);
    }

    private function detectListUnitPrice(DistributorProduct $offer, float $current): ?float
    {
        $candidates = [];

        if ($offer->retail_price !== null && (float) $offer->retail_price > $current + 0.009) {
            $candidates[] = (float) $offer->retail_price;
        }

        if ($offer->sourceProduct?->mark_on_sale) {
            $history = $offer->relationLoaded('priceHistories')
                ? $offer->priceHistories
                    ->where('price_type', DistributorProductPriceHistory::TYPE_RETAIL)
                    ->filter(fn ($row) => $row->old_price !== null && (float) $row->old_price > $current + 0.009)
                    ->sortByDesc(fn ($row) => $row->effective_at ?? $row->created_at)
                    ->first()
                : $offer->priceHistories()
                    ->where('price_type', DistributorProductPriceHistory::TYPE_RETAIL)
                    ->whereNotNull('old_price')
                    ->where('old_price', '>', $current)
                    ->latest('effective_at')
                    ->first();

            if ($history !== null) {
                $candidates[] = (float) $history->old_price;
            }
        }

        return $candidates === [] ? null : max($candidates);
    }
}
