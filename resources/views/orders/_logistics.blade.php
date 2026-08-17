{{-- Логистика по этапам заказа --}}
<section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 space-y-3 text-sm">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Логистика</h2>

    <p><span class="text-gray-500">Способ доставки:</span> <span class="text-gray-900 dark:text-white">{{ $order->deliveryMethod?->name ?? '—' }}</span></p>

    @if($order->distributorWarehouse)
        <div>
            <p class="text-gray-500">Склад поставки (дистрибьютор):</p>
            <p class="text-gray-900 dark:text-white font-medium">{{ $order->distributorWarehouse->name }}</p>
            @if($order->distributorWarehouse->address)
                <p class="text-gray-600 dark:text-gray-300">{{ $order->distributorWarehouse->address }}</p>
            @endif
        </div>
    @elseif($order->deliveryAddress)
        <div>
            <p class="text-gray-500">Склад получения:</p>
            <p class="text-gray-900 dark:text-white font-medium">{{ $order->deliveryAddress->name }}</p>
            <p class="text-gray-600 dark:text-gray-300">{{ $order->deliveryAddress->address }}</p>
        </div>
    @endif

    @if($order->buyer_comment)
        <div>
            <p class="text-gray-500">Комментарий покупателя:</p>
            <p class="text-gray-900 dark:text-white whitespace-pre-line">{{ $order->buyer_comment }}</p>
        </div>
    @endif

    @if($order->delivery_date || $order->delivery_time_from || $order->delivery_vehicle_type)
        @if($order->delivery_date)
            <p>
                <span class="text-gray-500">Ожидаемая дата доставки:</span>
                <span class="text-gray-900 dark:text-white">{{ $order->delivery_date->format('d.m.Y') }}</span>
                @if($order->delivery_time_from || $order->delivery_time_to)
                    <span class="text-gray-600 dark:text-gray-300">
                        ·
                        {{ $order->delivery_time_from ? \Illuminate\Support\Str::of($order->delivery_time_from)->substr(0, 5) : '—' }}
                        –
                        {{ $order->delivery_time_to ? \Illuminate\Support\Str::of($order->delivery_time_to)->substr(0, 5) : '—' }}
                    </span>
                @endif
            </p>
        @endif
        @if($order->delivery_vehicle_type)
            <p><span class="text-gray-500">Тип ТС:</span> <span class="text-gray-900 dark:text-white">{{ $order->delivery_vehicle_type }}</span></p>
        @endif
    @endif

    @if($order->transportCompany || $order->tracking_number || $order->shipped_at || $order->shipping_from_warehouse)
        <div class="pt-3 border-t border-gray-200 dark:border-gray-700 space-y-2">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Отгрузка</p>
            @if($order->transportCompany)
                <p><span class="text-gray-500">Перевозчик:</span> <span class="text-gray-900 dark:text-white">{{ $order->transportCompany->name }}</span></p>
            @endif
            @if($order->tracking_number)
                <p>
                    <span class="text-gray-500">ТТН / трек-номер:</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ $order->tracking_number }}</span>
                    @if($order->transportCompany?->getTrackingLink($order->tracking_number))
                        <a href="{{ $order->transportCompany->getTrackingLink($order->tracking_number) }}"
                           target="_blank" rel="noopener"
                           class="ml-2 text-[#c3242a] hover:underline text-xs">Отследить на сайте ТК</a>
                    @elseif($order->transportCompany?->website)
                        <a href="{{ $order->transportCompany->website }}"
                           target="_blank" rel="noopener"
                           class="ml-2 text-[#c3242a] hover:underline text-xs">Сайт ТК</a>
                    @endif
                </p>
            @endif
            @if($order->shipped_at)
                <p><span class="text-gray-500">Дата отправки:</span> <span class="text-gray-900 dark:text-white">{{ $order->shipped_at->format('d.m.Y H:i') }}</span></p>
            @endif
            @if($order->shipping_from_warehouse)
                <p><span class="text-gray-500">Склад отправления:</span> <span class="text-gray-900 dark:text-white">{{ $order->shipping_from_warehouse }}</span></p>
            @endif
        </div>
    @endif

    @if($order->received_at || $order->completion_notes || $order->has_active_claim)
        <div class="pt-3 border-t border-gray-200 dark:border-gray-700 space-y-2">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Завершение</p>
            @if($order->received_at)
                <p><span class="text-gray-500">Дата получения:</span> <span class="text-gray-900 dark:text-white">{{ $order->received_at->format('d.m.Y H:i') }}</span></p>
            @endif
            @if($order->completion_notes)
                <div>
                    <p class="text-gray-500">Замечания:</p>
                    <p class="text-gray-900 dark:text-white whitespace-pre-line">{{ $order->completion_notes }}</p>
                </div>
            @endif
            @if($order->has_active_claim)
                <p class="text-[#c3242a] font-medium">По заказу есть активная претензия / заявка на возврат</p>
            @endif
        </div>
    @endif
</section>
