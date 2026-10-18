<?php

namespace App\Services\Order;

use App\Models\PlatformOrder;
use App\Models\User;
use App\Notifications\OrderCreatedNotification;

class OrderCreatedNotifier
{
    /**
     * Уведомляет поставщика, который получает заказ: дистрибьютора по заказу конечной компании
     * и производителя после отправки закупки.
     */
    public function notifySupplier(PlatformOrder $order, ?User $except = null): void
    {
        $order->loadMissing([
            'distributorProfile.user',
            'manufacturerProfile.user',
            'endCompanyProfile',
        ]);

        $supplier = $order->isDistributorPurchase()
            ? $order->manufacturerProfile?->user
            : $order->distributorProfile?->user;

        if (! $supplier instanceof User) {
            return;
        }

        if ($except !== null && (int) $supplier->id === (int) $except->id) {
            return;
        }

        $buyerName = $order->isDistributorPurchase()
            ? ($order->distributorProfile?->displayName() ?? 'Дистрибьютор')
            : ($order->endCompanyProfile?->displayName() ?? 'Покупатель');

        $message = 'Новый заказ №'.$order->order_number.' от '.$buyerName;

        try {
            $supplier->notify(new OrderCreatedNotification($order, $message));
        } catch (\Throwable) {
            // Оформление заказа не зависит от доставки уведомления.
        }
    }
}
