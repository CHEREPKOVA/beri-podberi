@php
    $attributes = $categoryAttributes ?? collect();
    $showBasePrice = $showBasePrice ?? false;
    $logistics = $logistics ?? [];
@endphp

<section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Основные характеристики и технические параметры</h2>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                @if($showBasePrice)
                    <tr>
                        <td class="py-2 pr-4 text-gray-500 dark:text-gray-400 w-1/2">Базовая цена</td>
                        <td class="py-2 text-gray-900 dark:text-white font-medium">{{ $product->base_price ? number_format((float) $product->base_price, 2, ',', ' ') . ' ₽' : '—' }}</td>
                    </tr>
                @endif
                <tr>
                    <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">Единица измерения</td>
                    <td class="py-2 text-gray-900 dark:text-white">{{ $product->unitType?->name ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">Минимальная партия / кратность</td>
                    <td class="py-2 text-gray-900 dark:text-white">{{ $logistics['min_order_quantity'] ?? ($product->min_order_quantity ?? '—') }}</td>
                </tr>
                <tr>
                    <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">EAN / Штрихкод</td>
                    <td class="py-2 text-gray-900 dark:text-white">{{ $product->ean ?: '—' }}{{ $product->barcode ? ' / '.$product->barcode : '' }}</td>
                </tr>
                @if($product->distributor_sku)
                    <tr>
                        <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">Код дистрибьютора</td>
                        <td class="py-2 text-gray-900 dark:text-white">{{ $product->distributor_sku }}</td>
                    </tr>
                @endif
                @if($product->description)
                    <tr>
                        <td class="py-2 pr-4 text-gray-500 dark:text-gray-400 align-top">Описание</td>
                        <td class="py-2 text-gray-900 dark:text-white">{{ $product->description }}</td>
                    </tr>
                @endif
                <tr>
                    <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">Дата добавления в каталог</td>
                    <td class="py-2 text-gray-900 dark:text-white">{{ optional($product->created_at)->format('d.m.Y H:i') ?: '—' }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    @if($attributes->isNotEmpty())
        <div class="mt-5 border-t border-gray-200 dark:border-gray-700 pt-4">
            <h3 class="font-medium text-gray-900 dark:text-white mb-3">Параметры категории</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                        @foreach($attributes as $value)
                            <tr>
                                <td class="py-2 pr-4 text-gray-500 dark:text-gray-400 w-1/2">{{ $value->attribute->name }}</td>
                                <td class="py-2 text-gray-900 dark:text-white font-medium">{{ $value->value }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Дополнительные характеристики категории не заполнены.</p>
    @endif
</section>
