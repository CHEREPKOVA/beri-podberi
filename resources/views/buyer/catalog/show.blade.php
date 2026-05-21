@extends('layouts.app')

@section('title', 'Карточка товара')
@section('heading', 'Карточка товара')

@section('content')
<div class="space-y-6">
    <a href="{{ route('buyer.catalog.index', ['category' => $product->category?->slug]) }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-[#c3242a]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Назад в каталог
    </a>

    <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $product->name }}</h1>
                <p class="text-sm text-gray-500 mt-1">Артикул: {{ $product->sku }}</p>
                <p class="text-sm text-gray-600 mt-2">Поставщик: {{ $product->manufacturerProfile?->short_name ?: $product->manufacturerProfile?->full_name }}</p>
            </div>
            <p class="text-xl font-semibold text-[#c3242a]">{{ $product->base_price ? number_format((float) $product->base_price, 2, ',', ' ') . ' ₽' : '—' }}</p>
        </div>
    </section>

    <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
        <h2 class="text-lg font-semibold mb-4">Остатки по складам</h2>
        @if($companyRegionName)
            <p class="text-sm text-gray-500 mb-3">Показаны склады, подходящие для региона: {{ $companyRegionName }}</p>
        @endif
        @if($visibleStocks->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-gray-500 border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th class="text-left py-2 pr-4">Склад</th>
                            <th class="text-left py-2 pr-4">Доступно</th>
                            <th class="text-left py-2 pr-4">Обновлено</th>
                            <th class="text-left py-2">Условия отгрузки</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($visibleStocks as $stock)
                            <tr class="border-b border-gray-100 dark:border-gray-700/60">
                                <td class="py-2 pr-4 text-gray-900 dark:text-white">
                                    {{ $stock->warehouse?->name ?? 'Склад' }}
                                    @if($stock->warehouse?->region?->name)
                                        <span class="text-xs text-gray-500">({{ $stock->warehouse->region->name }})</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4 {{ $stock->available_quantity > 0 ? 'text-green-600' : 'text-amber-600' }}">{{ $stock->available_quantity }}</td>
                                <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">{{ optional($stock->stock_updated_at)->format('d.m.Y H:i') ?: '—' }}</td>
                                <td class="py-2 text-gray-700 dark:text-gray-300">{{ $stock->warehouse?->shipping_conditions ?: ($product->transport_conditions ?: 'Стандартные условия') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-gray-500">Нет доступных складов для вашего региона. Товар отображается как под заказ.</p>
        @endif
    </section>

    <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
        <h2 class="text-lg font-semibold mb-3">Аналоги</h2>
        @if(($productUnavailable ?? false) && ($analogs ?? collect())->isNotEmpty())
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                Товар недоступен. Доступные аналоги:
            </div>
        @endif

        @if(($analogs ?? collect())->isNotEmpty())
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach($analogs as $analog)
                    @php
                        $analogStocks = $analog->visibleStocksForRegion($companyRegionId ?? null);
                        $analogAvailable = $analogStocks->sum(fn ($s) => $s->available_quantity);
                        $analogPrice = $analog->getPriceForRegion($companyRegionId ?? null);
                        $attributePreview = $analog->attributeValues->take(3);
                    @endphp
                    <article class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="aspect-[4/3] bg-gray-100 dark:bg-gray-700">
                            <img src="{{ $analog->primaryImage()?->url ?? asset('images/placeholder-product.svg') }}" alt="" class="w-full h-full object-cover" />
                        </div>
                        <div class="p-3 space-y-1.5">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white line-clamp-2">{{ $analog->name }}</p>
                            <p class="text-xs text-gray-500">Артикул: {{ $analog->sku }}</p>
                            <div class="text-xs text-gray-600 dark:text-gray-300">
                                @forelse($attributePreview as $value)
                                    <p>{{ $value->attribute?->name }}: {{ $value->value }}</p>
                                @empty
                                    <p>Характеристики не указаны</p>
                                @endforelse
                            </div>
                            <p class="text-sm font-semibold text-[#c3242a] mt-1">{{ $analogPrice !== '0' ? number_format((float) $analogPrice, 2, ',', ' ') . ' ₽' : '—' }}</p>
                            <p class="text-xs {{ $analogAvailable > 0 ? 'text-green-600' : 'text-amber-600' }}">
                                {{ $analogAvailable > 0 ? "Остаток: {$analogAvailable}" : 'Нет в наличии (под заказ)' }}
                            </p>
                            <a href="{{ route('buyer.catalog.show', $analog) }}" class="inline-flex mt-1 text-sm text-[#c3242a] hover:text-[#a01e24]">Перейти в карточку</a>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-500">
                Аналоги не найдены для вашего региона или по условиям совместимости.
            </p>
        @endif
    </section>
</div>
@endsection
