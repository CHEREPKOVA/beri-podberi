<?php

namespace App\Services\Order;

use App\Models\DistributorProductStock;
use App\Models\PlatformOrder;
use App\Models\PlatformOrderItem;
use App\Models\ProductStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderStockReservationService
{
    public function reserve(PlatformOrder $order): void
    {
        if ($order->stock_reserved) {
            return;
        }

        $order->loadMissing('items');

        DB::transaction(function () use ($order): void {
            $locked = PlatformOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->stock_reserved) {
                return;
            }

            $allocations = [];
            foreach ($order->items as $item) {
                $allocations = array_merge($allocations, $this->reserveItem($order, $item));
            }

            $locked->stock_reserved = true;
            $locked->stock_reservation = $allocations;
            $locked->save();

            $order->stock_reserved = true;
            $order->stock_reservation = $allocations;
        });
    }

    public function release(PlatformOrder $order): void
    {
        if (! $order->stock_reserved) {
            return;
        }

        DB::transaction(function () use ($order): void {
            $locked = PlatformOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! $locked->stock_reserved) {
                return;
            }

            foreach ($this->allocations($locked) as $row) {
                $this->adjustReserved($row['type'], (int) $row['stock_id'], -(int) $row['qty']);
            }

            $locked->stock_reserved = false;
            $locked->stock_reservation = null;
            $locked->save();

            $order->stock_reserved = false;
            $order->stock_reservation = null;
        });
    }

    /**
     * Списание при отгрузке: уменьшает quantity и снимает reserved.
     */
    public function fulfill(PlatformOrder $order): void
    {
        if (! $order->stock_reserved) {
            return;
        }

        DB::transaction(function () use ($order): void {
            $locked = PlatformOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! $locked->stock_reserved) {
                return;
            }

            foreach ($this->allocations($locked) as $row) {
                $qty = (int) $row['qty'];
                $this->adjustReserved($row['type'], (int) $row['stock_id'], -$qty);
                $this->adjustQuantity($row['type'], (int) $row['stock_id'], -$qty);
            }

            $locked->stock_reserved = false;
            $locked->stock_reservation = null;
            $locked->save();

            $order->stock_reserved = false;
            $order->stock_reservation = null;
        });
    }

    /**
     * @return list<array{type: string, stock_id: int, qty: int, item_id: int}>
     */
    private function reserveItem(PlatformOrder $order, PlatformOrderItem $item): array
    {
        $need = (int) $item->quantity;
        if ($need < 1) {
            return [];
        }

        if ($order->isDistributorPurchase()) {
            return $this->reserveProductStock((int) $item->product_id, $need, (int) $item->id);
        }

        return $this->reserveDistributorStock(
            (int) $item->distributor_product_id,
            $item->distributor_warehouse_id ? (int) $item->distributor_warehouse_id : null,
            $need,
            (int) $item->id,
        );
    }

    /**
     * @return list<array{type: string, stock_id: int, qty: int, item_id: int}>
     */
    private function reserveDistributorStock(?int $offerId, ?int $warehouseId, int $need, int $itemId): array
    {
        if (! $offerId) {
            throw ValidationException::withMessages([
                'status' => 'Нельзя зарезервировать позицию без привязки к товару поставщика.',
            ]);
        }

        $query = DistributorProductStock::query()
            ->where('distributor_product_id', $offerId)
            ->lockForUpdate()
            ->orderByDesc(DB::raw('(quantity - reserved)'));

        if ($warehouseId) {
            $query->where('distributor_warehouse_id', $warehouseId);
        }

        $stocks = $query->get();
        $allocations = $this->allocateFromStocks($stocks, $need, 'distributor', $itemId);

        if ($allocations === []) {
            throw ValidationException::withMessages([
                'status' => 'Недостаточно остатков для резервирования позиций заказа. Измените состав или отклоните заказ.',
            ]);
        }

        return $allocations;
    }

    /**
     * @return list<array{type: string, stock_id: int, qty: int, item_id: int}>
     */
    private function reserveProductStock(?int $productId, int $need, int $itemId): array
    {
        if (! $productId) {
            throw ValidationException::withMessages([
                'status' => 'Нельзя зарезервировать позицию без привязки к товару.',
            ]);
        }

        $stocks = ProductStock::query()
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->orderByDesc(DB::raw('(quantity - reserved)'))
            ->get();

        $allocations = $this->allocateFromStocks($stocks, $need, 'product', $itemId);

        if ($allocations === []) {
            throw ValidationException::withMessages([
                'status' => 'Недостаточно остатков для резервирования позиций заказа. Измените состав или отклоните заказ.',
            ]);
        }

        return $allocations;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, DistributorProductStock|ProductStock>  $stocks
     * @return list<array{type: string, stock_id: int, qty: int, item_id: int}>
     */
    private function allocateFromStocks($stocks, int $need, string $type, int $itemId): array
    {
        $left = $need;
        $allocations = [];

        foreach ($stocks as $stock) {
            $available = max(0, (int) $stock->quantity - (int) $stock->reserved);
            if ($available < 1) {
                continue;
            }

            $take = min($available, $left);
            $stock->reserved = (int) $stock->reserved + $take;
            $stock->stock_updated_at = now();
            $stock->save();

            $allocations[] = [
                'type' => $type,
                'stock_id' => (int) $stock->id,
                'qty' => $take,
                'item_id' => $itemId,
            ];

            $left -= $take;
            if ($left < 1) {
                break;
            }
        }

        if ($left > 0) {
            // Откат частичных аллокаций этой позиции произойдёт вместе с rollback транзакции.
            return [];
        }

        return $allocations;
    }

    /**
     * @return list<array{type: string, stock_id: int, qty: int, item_id?: int}>
     */
    private function allocations(PlatformOrder $order): array
    {
        $raw = $order->stock_reservation;
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter($raw, static fn ($row): bool => is_array($row)
            && isset($row['type'], $row['stock_id'], $row['qty'])));
    }

    private function adjustReserved(string $type, int $stockId, int $delta): void
    {
        $stock = $this->lockStock($type, $stockId);
        $next = (int) $stock->reserved + $delta;
        if ($next < 0) {
            $next = 0;
        }
        $stock->reserved = $next;
        $stock->stock_updated_at = now();
        $stock->save();
    }

    private function adjustQuantity(string $type, int $stockId, int $delta): void
    {
        $stock = $this->lockStock($type, $stockId);
        $next = (int) $stock->quantity + $delta;
        if ($next < 0) {
            $next = 0;
        }
        $stock->quantity = $next;
        $stock->stock_updated_at = now();
        $stock->save();
    }

    private function lockStock(string $type, int $stockId): DistributorProductStock|ProductStock
    {
        if ($type === 'product') {
            return ProductStock::query()->whereKey($stockId)->lockForUpdate()->firstOrFail();
        }

        return DistributorProductStock::query()->whereKey($stockId)->lockForUpdate()->firstOrFail();
    }
}
