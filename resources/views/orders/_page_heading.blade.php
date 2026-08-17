@php
    use App\Models\PlatformOrder;
    use App\Services\Order\OrderStatusWorkflowService;

    $prefix = $titlePrefix ?? 'Заказ';
    $orderDate = $order->ordered_at ?? $order->created_at;
    $cancelledByBuyer = $order->status === PlatformOrder::STATUS_REJECTED
        && $order->relationLoaded('statusLogs')
        && $order->statusLogs->contains(
            fn ($log) => $log->action === OrderStatusWorkflowService::ACTION_CANCEL
                && $log->to_status === PlatformOrder::STATUS_REJECTED
        );
    $statusBadgeLabel = $cancelledByBuyer ? 'Отменён' : $order->statusLabel();
@endphp
<span>{{ $prefix }} {{ $order->order_number }}</span>
@if($orderDate)
    <span class="text-base font-normal text-gray-500 dark:text-gray-400">от {{ $orderDate->format('d.m.Y') }}</span>
@endif
<span class="px-2.5 py-1 rounded-md text-xs font-medium {{ $order->statusBadgeClass() }}">{{ $statusBadgeLabel }}</span>
@if($order->isPaused())
    <span class="px-2.5 py-1 rounded-md text-xs font-medium bg-amber-100 text-amber-900 dark:bg-amber-900/30 dark:text-amber-200">Приостановлен</span>
@endif
