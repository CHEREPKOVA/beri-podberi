<?php

namespace App\Services\Order;

use App\Models\PlatformOrder;
use App\Models\PlatformOrderItem;
use App\Models\PlatformOrderStatusLog;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\CatalogRegionService;
use Illuminate\Validation\ValidationException;

class BuyerOrderEditService
{
    public function __construct(
        private readonly CatalogRegionService $regionService,
    ) {}

    public function assertEditable(PlatformOrder $order): void
    {
        if (! in_array($order->status, [
            PlatformOrder::STATUS_NEW,
            PlatformOrder::STATUS_AWAITING_CONFIRMATION,
        ], true)) {
            throw ValidationException::withMessages([
                'order' => 'Редактирование доступно только до подтверждения производителем.',
            ]);
        }
    }

    public function updateQuantity(PlatformOrder $order, User $user, PlatformOrderItem $item, int $quantity): PlatformOrder
    {
        $this->assertEditable($order);
        $this->assertItemBelongs($order, $item);

        if ($quantity < 1) {
            return $this->removeItem($order, $user, $item);
        }

        $oldQty = (int) $item->quantity;
        $item->quantity = $quantity;
        $item->line_total = round((float) $item->unit_price * $quantity, 2);
        $item->save();

        $this->recalculateTotal($order);
        $this->logEdit($order, $user, "Изменено количество «{$item->name}»: {$oldQty} → {$quantity}");

        return $order->fresh(['items']);
    }

    public function removeItem(PlatformOrder $order, User $user, PlatformOrderItem $item): PlatformOrder
    {
        $this->assertEditable($order);
        $this->assertItemBelongs($order, $item);

        if ($order->items()->count() <= 1) {
            throw ValidationException::withMessages([
                'item' => 'Нельзя удалить последнюю позицию. Отмените заказ целиком.',
            ]);
        }

        $name = $item->name;
        $item->delete();
        $this->recalculateTotal($order);
        $this->logEdit($order, $user, "Удалена позиция «{$name}»");

        return $order->fresh(['items']);
    }

    public function addProduct(PlatformOrder $order, User $user, int $productId, int $quantity = 1): PlatformOrder
    {
        $this->assertEditable($order);

        if (! $order->isDistributorPurchase()) {
            throw ValidationException::withMessages([
                'product_id' => 'Добавление товаров доступно только для закупок у производителя.',
            ]);
        }

        $product = Product::query()
            ->with('manufacturerProfile')
            ->find($productId);

        if ($product === null || (int) $product->manufacturer_profile_id !== (int) $order->manufacturer_profile_id) {
            throw ValidationException::withMessages([
                'product_id' => 'Можно добавить только товары этого производителя.',
            ]);
        }

        $regionId = $this->regionService->resolveRegionId($user);
        $price = (float) $product->getPriceForRegion($regionId);
        if ($price <= 0) {
            throw ValidationException::withMessages([
                'product_id' => 'У товара не задана цена.',
            ]);
        }

        $quantity = max(1, $quantity);
        $minQty = max(1, (int) ($product->min_order_quantity ?? 1));
        if ($quantity < $minQty) {
            $quantity = $minQty;
        }

        $existing = $order->items()->where('product_id', $product->id)->first();
        if ($existing !== null) {
            return $this->updateQuantity($order, $user, $existing, (int) $existing->quantity + $quantity);
        }

        PlatformOrderItem::query()->create([
            'platform_order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'manufacturer_name' => $product->manufacturerProfile?->displayName(),
            'quantity' => $quantity,
            'min_order_quantity' => $minQty,
            'unit_price' => $price,
            'list_unit_price' => $price,
            'line_total' => round($price * $quantity, 2),
        ]);

        $this->recalculateTotal($order);
        $this->logEdit($order, $user, "Добавлена позиция «{$product->name}» × {$quantity}");

        return $order->fresh(['items']);
    }

    private function assertItemBelongs(PlatformOrder $order, PlatformOrderItem $item): void
    {
        if ((int) $item->platform_order_id !== (int) $order->id) {
            abort(404);
        }
    }

    private function recalculateTotal(PlatformOrder $order): void
    {
        $order->total_amount = round((float) $order->items()->sum('line_total'), 2);
        $order->save();
    }

    private function logEdit(PlatformOrder $order, User $user, string $comment): void
    {
        PlatformOrderStatusLog::query()->create([
            'platform_order_id' => $order->id,
            'from_status' => $order->status,
            'to_status' => $order->status,
            'action' => OrderStatusWorkflowService::ACTION_BUYER_EDIT,
            'comment' => $comment,
            'performed_by_user_id' => $user->id,
            'meta' => ['actor' => OrderStatusWorkflowService::ACTOR_BUYER],
        ]);
    }
}
