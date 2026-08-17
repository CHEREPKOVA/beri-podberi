{{-- Журнал действий по заказу --}}
@php
    use App\Models\PlatformOrder;
    use App\Services\Order\OrderStatusWorkflowService;
    $actionLabels = $action_labels ?? OrderStatusWorkflowService::actionLabels();
    $statusLabels = PlatformOrder::fallbackStatusLabels();
    $hideService = $hide_service ?? true;
    $title = $title ?? 'История изменений';
@endphp
<section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ $title }}</h2>
    @if($order->statusLogs->isEmpty())
        <p class="text-sm text-gray-500">Событий пока нет.</p>
    @else
        <ul class="space-y-3">
            @foreach($order->statusLogs as $log)
                @php
                    $meta = is_array($log->meta) ? $log->meta : [];
                    $isService = ($meta['service'] ?? false) === true
                        || in_array($log->action, [
                            OrderStatusWorkflowService::ACTION_SERVICE_COMMENT,
                            OrderStatusWorkflowService::ACTION_CONTACT_PARTIES,
                            OrderStatusWorkflowService::ACTION_PAUSE,
                            OrderStatusWorkflowService::ACTION_RESUME,
                            OrderStatusWorkflowService::ACTION_ADMIN_STATUS_CHANGE,
                        ], true);
                @endphp
                @continue($hideService && $isService)
                <li class="border border-gray-100 dark:border-gray-700 rounded-lg p-3 {{ $isService ? 'bg-amber-50/50 dark:bg-amber-900/10' : '' }}">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ $actionLabels[$log->action] ?? ($log->action ?: 'Событие') }}
                            </p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ $statusLabels[$log->from_status] ?? ($log->from_status ?: '—') }}
                                →
                                {{ $statusLabels[$log->to_status] ?? ($log->to_status ?: '—') }}
                            </p>
                        </div>
                        <p class="text-[11px] text-gray-400 whitespace-nowrap">
                            {{ $log->created_at?->format('d.m.Y H:i') }}
                            @if($log->performedBy)
                                · {{ $log->performedBy->name }}
                            @endif
                        </p>
                    </div>
                    @if($log->comment)
                        <p class="mt-2 text-sm text-gray-700 dark:text-gray-200 whitespace-pre-line">{{ $log->comment }}</p>
                    @endif
                    @if($isService && ! $hideService)
                        <p class="mt-1 text-[10px] uppercase tracking-wide text-amber-700 dark:text-amber-300">Служебная отметка</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>
