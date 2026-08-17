{{-- Состав заказа: таблица позиций по ТЗ 4.4 --}}
@php
    $showProductLinks = $show_product_links ?? true;
@endphp
<section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Состав заказа</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-[40rem] w-full text-sm table-fixed">
            <thead class="text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-900/30">
                <tr>
                    <th class="text-left py-3 px-5 font-medium w-[46%]">Товар</th>
                    <th class="text-right py-3 px-3 font-medium w-[14%]">Цена</th>
                    <th class="text-right py-3 px-3 font-medium w-[12%]">Кол-во</th>
                    <th class="text-right py-3 px-3 font-medium w-[14%]">Скидка</th>
                    <th class="text-right py-3 px-5 font-medium w-[14%]">Сумма</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    @php
                        $thumb = $item->thumbnailUrl();
                        $catalogUrl = $showProductLinks ? $item->catalogUrl() : null;
                        $multiplicity = $item->multiplicityLabel();
                        $discount = $item->discountAmount();
                    @endphp
                    <tr class="border-b border-gray-100 dark:border-gray-700/60 align-top">
                        <td class="py-3 px-5">
                            <div class="flex gap-3 items-start min-w-0">
                                @if($thumb)
                                    <img src="{{ $thumb }}" alt="" class="w-12 h-12 rounded object-cover border border-gray-200 dark:border-gray-600 shrink-0">
                                @endif
                                <div class="min-w-0">
                                    @if($catalogUrl)
                                        <a href="{{ $catalogUrl }}" class="font-medium text-gray-900 dark:text-white hover:text-[#c3242a] break-words">
                                            {{ $item->name }}
                                        </a>
                                    @else
                                        <p class="font-medium text-gray-900 dark:text-white break-words">{{ $item->name }}</p>
                                    @endif
                                    <p class="text-xs text-gray-500 mt-0.5 break-words">
                                        Артикул: {{ $item->sku ?: '—' }}
                                        @if($item->manufacturer_name)
                                            · {{ $item->manufacturer_name }}
                                        @endif
                                    </p>
                                    @if($item->warehouse)
                                        <p class="text-xs text-gray-500 mt-0.5">Склад: {{ $item->warehouse->name }}</p>
                                    @endif
                                    @if($multiplicity)
                                        <p class="text-xs text-gray-500 mt-0.5">{{ $multiplicity }}</p>
                                    @endif
                                    @if($catalogUrl)
                                        <a href="{{ $catalogUrl }}" class="inline-block mt-1 text-xs text-[#c3242a] hover:underline">Карточка товара</a>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-3 text-right whitespace-nowrap">
                            @if($item->hasDiscount())
                                <span class="block text-xs text-gray-400 line-through">{{ number_format((float) $item->list_unit_price, 2, ',', ' ') }} ₽</span>
                            @endif
                            {{ number_format((float) $item->unit_price, 2, ',', ' ') }} ₽
                        </td>
                        <td class="py-3 px-3 text-right">{{ $item->quantity }}</td>
                        <td class="py-3 px-3 text-right whitespace-nowrap">
                            @if($discount > 0)
                                −{{ number_format($discount, 2, ',', ' ') }} ₽
                            @else
                                —
                            @endif
                        </td>
                        <td class="py-3 px-5 text-right font-medium whitespace-nowrap">{{ number_format((float) $item->line_total, 2, ',', ' ') }} ₽</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
