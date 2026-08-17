<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DistributorProduct;
use App\Models\DistributorProductPriceHistory;
use App\Models\DistributorProfile;
use App\Models\EndCompanyProfile;
use App\Models\User;
use App\Services\Catalog\CatalogRegionService;
use App\Services\Catalog\EndCompanyCatalogSettings;
use App\Services\Catalog\EndCompanyDistributorOfferService;
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
    ) {}

    public function getOrCreateCart(EndCompanyProfile $profile): Cart
    {
        return Cart::query()->firstOrCreate([
            'end_company_profile_id' => $profile->id,
        ]);
    }

    public function resolveProfile(User $user): EndCompanyProfile
    {
        $profile = $user->endCompanyProfile ?? $user->getOrCreateEndCompanyProfile();

        return $profile;
    }

    /**
     * @return array{
     *     cart: Cart,
     *     groups: Collection<int, array<string, mixed>>,
     *     totals: array{groups_count: int, items_count: int, total_amount: float},
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
                'distributorProduct.profile',
                'distributorProduct.manufacturerProfile',
                'distributorProduct.sourceProduct.manufacturerProfile',
                'distributorProduct.stocks.warehouse.region',
                'distributorProduct.regionalPrices',
                'distributorProduct.priceHistories',
            ])
            ->orderBy('id')
            ->get();

        $enriched = $items->map(fn (CartItem $item): array => $this->enrichItem($item, $offerService, $regionId));

        $groups = $enriched
            ->groupBy(fn (array $row): int => (int) $row['distributor_profile_id'])
            ->map(function (Collection $groupItems, int $distributorProfileId): array {
                $first = $groupItems->first();

                return [
                    'distributor_profile_id' => $distributorProfileId,
                    'distributor_name' => $first['distributor_name'] ?? 'Поставщик',
                    'items' => $groupItems->values(),
                    'items_count' => $groupItems->count(),
                    'subtotal' => round($groupItems->sum(fn (array $row): float => (float) $row['line_total']), 2),
                    'discount_amount' => round($groupItems->sum(fn (array $row): float => (float) ($row['discount_amount'] ?? 0)), 2),
                    'has_blocking_warnings' => $groupItems->contains(
                        fn (array $row): bool => collect($row['warnings'])->contains(fn (array $w): bool => $w['blocking'])
                    ),
                ];
            })
            ->values();

        $hasBlocking = $groups->contains(fn (array $group): bool => $group['has_blocking_warnings']);

        return [
            'cart' => $cart,
            'groups' => $groups,
            'totals' => [
                'groups_count' => $groups->count(),
                'items_count' => $enriched->count(),
                'total_amount' => round($enriched->sum(fn (array $row): float => (float) $row['line_total']), 2),
                'discount_amount' => round($enriched->sum(fn (array $row): float => (float) ($row['discount_amount'] ?? 0)), 2),
            ],
            'has_blocking_warnings' => $hasBlocking,
        ];
    }

    public function itemsCount(User $user): int
    {
        $profile = $user->endCompanyProfile;
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

        $existing = $cart->items()
            ->where('distributor_product_id', $offer->id)
            ->first();

        $resultingQuantity = $existing
            ? ((int) $existing->quantity + $quantity)
            : $quantity;

        $this->assertQuantityConstraints($offer, $resultingQuantity);

        if ($existing) {
            $existing->quantity = $resultingQuantity;
            $existing->unit_price = $price;
            $existing->list_unit_price = $listPrice;
            $existing->save();

            return $existing->fresh('distributorProduct');
        }

        return $cart->items()->create([
            'distributor_product_id' => $offer->id,
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
    public function repeatFromOrder(User $user, \App\Models\PlatformOrder $order): array
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

        $regionId = $this->regionService->resolveRegionId($user);
        $offerService = new EndCompanyDistributorOfferService($regionId);
        $offer = $this->loadOffer($item->distributor_product_id);
        $this->assertQuantityConstraints($offer, $quantity);
        $price = $offerService->effectiveRetailPrice($offer);
        $listPrice = $this->resolveListUnitPrice($offer, $offerService);

        $item->quantity = $quantity;
        if ($price !== null && $price > 0) {
            $item->unit_price = $price;
        }
        $item->list_unit_price = $listPrice;
        $item->save();

        return $item->fresh('distributorProduct');
    }

    public function remove(User $user, CartItem $item): void
    {
        $this->assertOwnsItem($user, $item);
        $item->delete();
    }

    /**
     * @return array{
     *     groups: Collection<int, array<string, mixed>>,
     *     totals: array{groups_count: int, items_count: int, total_amount: float, discount_amount: float},
     *     has_blocking_warnings: bool,
     *     refreshed_at: string
     * }
     */
    public function live(User $user): array
    {
        $view = $this->view($user);

        return [
            'groups' => $view['groups'],
            'totals' => $view['totals'],
            'has_blocking_warnings' => $view['has_blocking_warnings'],
            'refreshed_at' => now()->format('d.m.Y H:i:s'),
        ];
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
     * @return array<string, mixed>
     */
    private function enrichItem(
        CartItem $item,
        EndCompanyDistributorOfferService $offerService,
        ?int $regionId,
    ): array {
        $offer = $item->distributorProduct;
        $warnings = $this->buildWarnings($item, $offer, $offerService, $regionId);
        $currentPrice = $offer ? $offerService->effectiveRetailPrice($offer) : null;
        $displayPrice = $currentPrice ?? (float) $item->unit_price;
        $listPrice = $this->resolveListUnitPriceFromStored($item, $offer, $offerService, $displayPrice);
        $hasDiscount = $listPrice !== null && $listPrice > $displayPrice + 0.009;
        $discountAmount = $hasDiscount
            ? round(($listPrice - $displayPrice) * $item->quantity, 2)
            : 0.0;
        $availableStock = $offer ? $offerService->availableStockForOffer($offer) : 0;

        return [
            'id' => $item->id,
            'distributor_product_id' => $item->distributor_product_id,
            'distributor_profile_id' => (int) ($offer?->distributor_profile_id ?? 0),
            'distributor_name' => $offer?->profile?->displayName() ?? 'Поставщик',
            'manufacturer_profile_id' => $offer?->manufacturer_profile_id,
            'product_id' => $offer?->source_product_id,
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
