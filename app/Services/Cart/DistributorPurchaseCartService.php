<?php

namespace App\Services\Cart;

use App\Models\DistributorProfile;
use App\Models\DistributorPurchaseCart;
use App\Models\DistributorPurchaseCartItem;
use App\Models\ManufacturerDistributorPartnership;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\CatalogRegionService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DistributorPurchaseCartService
{
    public const WARNING_OUT_OF_STOCK = 'out_of_stock';

    public const WARNING_PRICE_CHANGED = 'price_changed';

    public const WARNING_MIN_QUANTITY = 'min_quantity';

    public const WARNING_NO_PARTNERSHIP = 'no_partnership';

    public const WARNING_NO_PRICE = 'no_price';

    public function __construct(
        private readonly CatalogRegionService $regionService,
    ) {}

    public function resolveProfile(User $user): DistributorProfile
    {
        $profile = $user->distributorProfile;
        if ($profile === null) {
            throw ValidationException::withMessages([
                'cart' => 'Профиль дистрибьютора не найден.',
            ]);
        }

        return $profile;
    }

    public function getOrCreateCart(DistributorProfile $profile): DistributorPurchaseCart
    {
        return DistributorPurchaseCart::query()->firstOrCreate([
            'distributor_profile_id' => $profile->id,
        ]);
    }

    /**
     * @return array{
     *     cart: DistributorPurchaseCart,
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

        $items = $cart->items()
            ->with(['product.manufacturerProfile', 'product.stocks.warehouse'])
            ->orderBy('id')
            ->get();

        $enriched = $items->map(fn (DistributorPurchaseCartItem $item): array => $this->enrichItem($profile, $item, $regionId));

        $groups = $enriched
            ->groupBy(fn (array $row): int => (int) $row['manufacturer_profile_id'])
            ->map(function (Collection $groupItems, int $manufacturerProfileId): array {
                $first = $groupItems->first();

                return [
                    'manufacturer_profile_id' => $manufacturerProfileId,
                    'manufacturer_name' => $first['manufacturer_name'] ?? 'Производитель',
                    'manufacturer_logo_url' => $first['manufacturer_logo_url'] ?? null,
                    'items' => $groupItems->values(),
                    'items_count' => $groupItems->count(),
                    'subtotal' => round($groupItems->sum(fn (array $row): float => (float) $row['line_total']), 2),
                    'has_blocking_warnings' => $groupItems->contains(
                        fn (array $row): bool => collect($row['warnings'])->contains(fn (array $w): bool => $w['blocking'])
                    ),
                ];
            })
            ->values();

        return [
            'cart' => $cart,
            'groups' => $groups,
            'totals' => [
                'groups_count' => $groups->count(),
                'items_count' => $enriched->count(),
                'total_amount' => round($enriched->sum(fn (array $row): float => (float) $row['line_total']), 2),
            ],
            'has_blocking_warnings' => $groups->contains(fn (array $g): bool => $g['has_blocking_warnings']),
        ];
    }

    public function itemsCount(User $user): int
    {
        $profile = $user->distributorProfile;
        if ($profile === null) {
            return 0;
        }

        $cart = DistributorPurchaseCart::query()
            ->where('distributor_profile_id', $profile->id)
            ->first();

        return $cart?->items()->count() ?? 0;
    }

    public function add(User $user, int $productId, int $quantity = 1): DistributorPurchaseCartItem
    {
        $profile = $this->resolveProfile($user);
        $regionId = $this->regionService->resolveRegionId($user);
        $product = $this->requirePurchasableProduct($profile, $productId, $regionId);
        $quantity = max(1, $quantity);
        $minQty = max(1, (int) ($product->min_order_quantity ?? 1));
        if ($quantity < $minQty) {
            $quantity = $minQty;
        }

        $cart = $this->getOrCreateCart($profile);
        $price = (float) $product->getPriceForRegion($regionId);

        $item = DistributorPurchaseCartItem::query()->firstOrNew([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
        ]);

        $item->quantity = ($item->exists ? (int) $item->quantity : 0) + $quantity;
        $item->unit_price = $price;
        $item->save();

        return $item;
    }

    public function updateQuantity(User $user, DistributorPurchaseCartItem $item, int $quantity): DistributorPurchaseCartItem
    {
        $this->assertOwnsItem($user, $item);

        if ($quantity < 1) {
            $item->delete();

            return $item;
        }

        $profile = $this->resolveProfile($user);
        $regionId = $this->regionService->resolveRegionId($user);
        $product = $this->requirePurchasableProduct($profile, (int) $item->product_id, $regionId);
        $minQty = max(1, (int) ($product->min_order_quantity ?? 1));
        if ($quantity < $minQty) {
            throw ValidationException::withMessages([
                'quantity' => "Минимальное количество для заказа: {$minQty}.",
            ]);
        }

        $item->quantity = $quantity;
        $item->unit_price = (float) $product->getPriceForRegion($regionId);
        $item->save();

        return $item;
    }

    public function remove(User $user, DistributorPurchaseCartItem $item): void
    {
        $this->assertOwnsItem($user, $item);
        $item->delete();
    }

    public function clearGroup(User $user, int $manufacturerProfileId): void
    {
        $profile = $this->resolveProfile($user);
        $cart = $this->getOrCreateCart($profile);

        $itemIds = $cart->items()
            ->whereHas('product', fn ($q) => $q->where('manufacturer_profile_id', $manufacturerProfileId))
            ->pluck('id');

        if ($itemIds->isNotEmpty()) {
            DistributorPurchaseCartItem::query()->whereIn('id', $itemIds)->delete();
        }
    }

    /**
     * @return array{added: int, skipped: list<string>}
     */
    public function repeatFromOrder(User $user, \App\Models\PlatformOrder $order): array
    {
        $profile = $this->resolveProfile($user);
        if ((int) $order->distributor_profile_id !== (int) $profile->id || ! $order->isDistributorPurchase()) {
            throw ValidationException::withMessages([
                'order' => 'Нельзя повторить чужой заказ.',
            ]);
        }

        $added = 0;
        $skipped = [];
        $regionId = $this->regionService->resolveRegionId($user);

        foreach ($order->items as $orderItem) {
            if ($orderItem->product_id === null) {
                $skipped[] = ($orderItem->name ?: 'Позиция').' — нет товара каталога';

                continue;
            }

            try {
                $this->requirePurchasableProduct($profile, (int) $orderItem->product_id, $regionId);
                $this->add($user, (int) $orderItem->product_id, max(1, (int) $orderItem->quantity));
                $added++;
            } catch (ValidationException $e) {
                $skipped[] = ($orderItem->name ?: 'Позиция').' — '.collect($e->errors())->flatten()->first();
            }
        }

        return compact('added', 'skipped');
    }

    /**
     * @return array<string, mixed>
     */
    private function enrichItem(DistributorProfile $profile, DistributorPurchaseCartItem $item, ?int $regionId): array
    {
        $product = $item->product;
        $warnings = [];
        $manufacturer = $product?->manufacturerProfile;
        $manufacturerId = (int) ($product?->manufacturer_profile_id ?? 0);
        $livePrice = $product !== null ? (float) $product->getPriceForRegion($regionId) : 0.0;
        $stock = $product !== null ? $product->getAvailableStockInRegion($regionId) : 0;
        $minQty = max(1, (int) ($product?->min_order_quantity ?? 1));
        $hasPartnership = $manufacturerId > 0 && $this->hasActivePartnership($profile, $manufacturerId);

        if (! $hasPartnership) {
            $warnings[] = ['code' => self::WARNING_NO_PARTNERSHIP, 'message' => 'Нет активного партнёрства с производителем', 'blocking' => true];
        }
        if ($livePrice <= 0) {
            $warnings[] = ['code' => self::WARNING_NO_PRICE, 'message' => 'Цена не задана', 'blocking' => true];
        }
        if ($stock < (int) $item->quantity) {
            $warnings[] = ['code' => self::WARNING_OUT_OF_STOCK, 'message' => "Доступно только {$stock} шт.", 'blocking' => true];
        }
        if ((int) $item->quantity < $minQty) {
            $warnings[] = ['code' => self::WARNING_MIN_QUANTITY, 'message' => "Минимум {$minQty} шт.", 'blocking' => true];
        }
        if ($item->unit_price !== null && abs((float) $item->unit_price - $livePrice) > 0.009) {
            $warnings[] = [
                'code' => self::WARNING_PRICE_CHANGED,
                'message' => 'Цена изменилась: было '.number_format((float) $item->unit_price, 2, ',', ' ').' ₽, сейчас '.number_format($livePrice, 2, ',', ' ').' ₽',
                'blocking' => false,
            ];
        }

        $unitPrice = $livePrice > 0 ? $livePrice : (float) ($item->unit_price ?? 0);

        return [
            'cart_item_id' => $item->id,
            'product_id' => $product?->id,
            'name' => $product?->name ?? 'Товар',
            'sku' => $product?->sku,
            'manufacturer_profile_id' => $manufacturerId,
            'manufacturer_name' => $manufacturer?->displayName() ?? 'Производитель',
            'manufacturer_logo_url' => $manufacturer?->logo_url,
            'quantity' => (int) $item->quantity,
            'unit_price' => $unitPrice,
            'line_total' => round($unitPrice * (int) $item->quantity, 2),
            'available_stock' => $stock,
            'min_order_quantity' => $minQty,
            'pack_quantity' => $product?->attributeValueBySlug('kolichestvo-v-upakovke'),
            'warnings' => $warnings,
        ];
    }

    private function requirePurchasableProduct(DistributorProfile $profile, int $productId, ?int $regionId): Product
    {
        $product = Product::query()
            ->with('manufacturerProfile')
            ->visibleInCatalog()
            ->find($productId);

        if ($product === null) {
            throw ValidationException::withMessages([
                'product_id' => 'Товар не найден в каталоге.',
            ]);
        }

        $manufacturerId = (int) $product->manufacturer_profile_id;
        if ($manufacturerId < 1 || ! $this->hasActivePartnership($profile, $manufacturerId)) {
            throw ValidationException::withMessages([
                'product_id' => 'Нет активного партнёрства с производителем этого товара.',
            ]);
        }

        $price = (float) $product->getPriceForRegion($regionId);
        if ($price <= 0) {
            throw ValidationException::withMessages([
                'product_id' => 'У товара не задана цена закупки.',
            ]);
        }

        return $product;
    }

    private function hasActivePartnership(DistributorProfile $profile, int $manufacturerProfileId): bool
    {
        return ManufacturerDistributorPartnership::query()
            ->where('distributor_profile_id', $profile->id)
            ->where('manufacturer_profile_id', $manufacturerProfileId)
            ->where('status', ManufacturerDistributorPartnership::STATUS_ACTIVE)
            ->exists();
    }

    private function assertOwnsItem(User $user, DistributorPurchaseCartItem $item): void
    {
        $profile = $this->resolveProfile($user);
        $item->loadMissing('cart');

        if ((int) $item->cart?->distributor_profile_id !== (int) $profile->id) {
            abort(403);
        }
    }
}
