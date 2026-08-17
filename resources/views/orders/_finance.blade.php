{{-- Финансовые параметры заказа --}}
@php
    $discountTotal = $order->discountTotal();
    $listTotal = $order->listTotal();
@endphp
<section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 space-y-3 text-sm">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Стоимость</h2>
    @if($discountTotal > 0)
        <p class="flex justify-between gap-3">
            <span class="text-gray-500">Сумма без скидки</span>
            <span class="text-gray-900 dark:text-white">{{ number_format($listTotal, 2, ',', ' ') }} ₽</span>
        </p>
        <p class="flex justify-between gap-3">
            <span class="text-gray-500">Скидка</span>
            <span class="text-[#c3242a]">−{{ number_format($discountTotal, 2, ',', ' ') }} ₽</span>
        </p>
    @endif
    <div class="pt-2 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
        <span class="text-gray-500">Итого</span>
        <span class="text-xl font-semibold text-[#c3242a]">{{ number_format((float) $order->total_amount, 2, ',', ' ') }} ₽</span>
    </div>
    <p class="text-xs text-gray-500">Валюта: российский рубль (₽)</p>
</section>
