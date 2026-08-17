@php
    use App\Models\PlatformOrder;
    use App\Services\Order\OrderStatusWorkflowService;
    $progress = PlatformOrder::progressStatuses();
    $labels = PlatformOrder::fallbackStatusLabels();
    $currentIndex = $progressIndex;
    $isRejected = $order->status === PlatformOrder::STATUS_REJECTED;
    $isPaused = $order->isPaused();
    $cancelledByBuyer = $isRejected && $order->relationLoaded('statusLogs') && $order->statusLogs->contains(
        fn ($log) => $log->action === OrderStatusWorkflowService::ACTION_CANCEL
            && $log->to_status === PlatformOrder::STATUS_REJECTED
    );
    $rejectionReason = trim((string) ($order->rejection_reason ?? ''));
    $defaultBuyerCancelReason = 'Заказ отменён покупателем';
    $showRejectionAlert = $isRejected && (
        ($rejectionReason !== '' && ! ($cancelledByBuyer && $rejectionReason === $defaultBuyerCancelReason))
        || ($rejectionReason === '' && ! $cancelledByBuyer)
    );
@endphp

<section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Статус заказа</h2>

    @if($isPaused)
        <div class="rounded-lg border border-amber-400/50 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-500/40 dark:bg-amber-900/20 dark:text-amber-100 mb-4">
            <p class="font-medium">Заказ приостановлен</p>
            @if($order->pause_reason)
                <p class="mt-1">{{ $order->pause_reason }}</p>
            @endif
            @if($order->paused_at || $order->pausedBy)
                <p class="mt-1 text-xs opacity-80">
                    {{ optional($order->paused_at)->format('d.m.Y H:i') }}
                    @if($order->pausedBy)
                        · {{ $order->pausedBy->name }}
                    @endif
                </p>
            @endif
        </div>
    @endif

    @if($showRejectionAlert)
        <div class="rounded-lg border border-[#c3242a]/40 bg-red-50 px-4 py-3 text-sm text-[#a01e24] dark:border-[#c3242a]/50 dark:bg-red-900/20 dark:text-red-100 mb-4">
            <p class="font-medium">{{ $rejectionReason !== '' ? $rejectionReason : 'Заказ отклонён' }}</p>
        </div>
    @endif

    <ol class="relative flex flex-col sm:flex-row sm:items-start gap-4 sm:gap-0">
        @foreach($progress as $index => $slug)
            @php
                $done = $currentIndex !== null && $index < $currentIndex;
                $current = $currentIndex !== null && $index === $currentIndex && ! $isRejected;
                $leftActive = $currentIndex !== null && $index <= $currentIndex && ! $isRejected;
                $rightActive = $currentIndex !== null && $index < $currentIndex && ! $isRejected;
            @endphp
            <li class="flex-1 relative sm:text-center min-w-0">
                {{-- Полоски по обе стороны кружка (на десктопе) --}}
                <span class="hidden sm:block absolute top-3 left-0 right-1/2 h-0.5 {{ $leftActive ? 'bg-[#c3242a]' : 'bg-gray-200 dark:bg-gray-600' }}"></span>
                <span class="hidden sm:block absolute top-3 left-1/2 right-0 h-0.5 {{ $rightActive ? 'bg-[#c3242a]' : 'bg-gray-200 dark:bg-gray-600' }}"></span>

                <div class="relative z-10 flex sm:flex-col items-center gap-2 sm:gap-1">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full text-[10px] font-semibold ring-2 ring-white dark:ring-gray-800
                        {{ $done || $current ? 'bg-[#c3242a] text-white' : 'bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }}
                        {{ $current ? 'ring-[#c3242a]/30' : '' }}">
                        {{ $index + 1 }}
                    </span>
                    <span class="text-xs {{ $current ? 'font-semibold text-[#c3242a]' : ($done ? 'font-medium text-gray-700 dark:text-gray-300' : 'text-gray-500 dark:text-gray-400') }}">
                        {{ $labels[$slug] ?? $slug }}
                    </span>
                </div>
            </li>
        @endforeach
    </ol>

    @if($order->tracking_number || $order->shipped_at || $order->shipping_from_warehouse || $order->received_at)
        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 text-sm space-y-1">
            @if($order->tracking_number)
                <p>
                    <span class="text-gray-500">ТТН / трек:</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ $order->tracking_number }}</span>
                    @if($order->transportCompany?->getTrackingLink($order->tracking_number))
                        <a href="{{ $order->transportCompany->getTrackingLink($order->tracking_number) }}"
                           target="_blank" rel="noopener"
                           class="ml-2 text-[#c3242a] hover:underline text-xs">Отследить</a>
                    @endif
                </p>
            @endif
            @if($order->shipped_at)
                <p><span class="text-gray-500">Отправлен:</span> <span class="text-gray-900 dark:text-white">{{ $order->shipped_at->format('d.m.Y H:i') }}</span></p>
            @endif
            @if($order->shipping_from_warehouse)
                <p><span class="text-gray-500">Склад отправления:</span> <span class="text-gray-900 dark:text-white">{{ $order->shipping_from_warehouse }}</span></p>
            @endif
            @if($order->transportCompany)
                <p><span class="text-gray-500">ТК:</span> <span class="text-gray-900 dark:text-white">{{ $order->transportCompany->name }}</span></p>
            @endif
            @if($order->received_at)
                <p><span class="text-gray-500">Получен:</span> <span class="text-gray-900 dark:text-white">{{ $order->received_at->format('d.m.Y H:i') }}</span></p>
            @endif
        </div>
    @endif
</section>
