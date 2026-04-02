@extends('layouts.app')

@section('title', 'Карточка товара')
@section('heading', 'Карточка товара')

@section('content')
@php
    $primaryImage = $product->primaryImage();
    $images = $product->images;
    $attributes = $product->attributeValues->filter(fn ($item) => $item->attribute);
    $stocks = $product->stocks->sortByDesc('stock_updated_at');
    $documents = $product->documents;
    $analogs = $product->analogs;
@endphp

<div class="space-y-6">
    @include('admin.partials.flash')

    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.catalog.products.index') }}"
           class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-[#c3242a]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Назад к списку товаров
        </a>
        <a href="{{ route('admin.catalog.products.edit', $product) }}"
           class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-[#c3242a] text-white hover:bg-[#a01e24]">
            Редактировать карточку
        </a>
    </div>

    <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $product->name }}</h1>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                    <span class="px-2 py-1 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">Артикул: {{ $product->sku }}</span>
                    @if($product->manufacturer_sku)
                        <span class="px-2 py-1 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">Код производителя: {{ $product->manufacturer_sku }}</span>
                    @endif
                    <span class="px-2 py-1 rounded-md {{ $product->statusBadgeClass() }}">{{ $product->statusLabel() }}</span>
                </div>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                    Производитель: {{ $product->manufacturerProfile?->short_name ?: $product->manufacturerProfile?->full_name ?: '—' }}
                </p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    Категория: {{ $product->category?->name ?? 'Не назначена' }}
                    @if($product->additionalCategories->isNotEmpty())
                        · Подкатегории: {{ $product->additionalCategories->pluck('name')->implode(', ') }}
                    @endif
                </p>
            </div>
            <div class="text-sm text-gray-500 dark:text-gray-400 text-right">
                <p>Публикация: {{ $product->show_in_catalog ? 'В каталоге' : 'Скрыт из каталога' }}</p>
                <p>Последнее обновление: {{ optional($product->updated_at)->format('d.m.Y H:i') ?: '—' }}</p>
                <p>Добавлен: {{ optional($product->created_at)->format('d.m.Y H:i') ?: '—' }}</p>
            </div>
        </div>
    </section>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <section class="xl:col-span-1 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5"
                 x-data="{ activeImage: '{{ $primaryImage?->url ?? asset('images/placeholder-product.svg') }}' }">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Фотогалерея</h2>
            <div class="aspect-square bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden flex items-center justify-center">
                <a :href="activeImage" target="_blank" rel="noopener" class="block w-full h-full">
                    <img :src="activeImage" alt="{{ $product->name }}" class="w-full h-full object-cover" />
                </a>
            </div>
            @if($images->isNotEmpty())
                <div class="grid grid-cols-4 gap-2 mt-3">
                    @foreach($images as $image)
                        <button type="button" @click="activeImage = '{{ $image->url }}'"
                                class="aspect-square rounded-lg overflow-hidden border border-gray-200 dark:border-gray-600 hover:border-[#c3242a]">
                            <img src="{{ $image->url }}" alt="" class="w-full h-full object-cover" />
                        </button>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-3">Изображения не добавлены. Показана заглушка.</p>
            @endif
        </section>

        <section class="xl:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Основные характеристики и технические параметры</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-2 text-sm">
                <div class="text-gray-500 dark:text-gray-400">Базовая цена</div><div class="text-gray-900 dark:text-white font-medium">{{ $product->base_price ? number_format((float) $product->base_price, 2, ',', ' ') . ' ₽' : '—' }}</div>
                <div class="text-gray-500 dark:text-gray-400">Единица измерения</div><div class="text-gray-900 dark:text-white">{{ $product->unitType?->name ?? '—' }}</div>
                <div class="text-gray-500 dark:text-gray-400">Минимальная партия</div><div class="text-gray-900 dark:text-white">{{ $product->min_order_quantity ?? '—' }}</div>
                <div class="text-gray-500 dark:text-gray-400">EAN / Штрихкод</div><div class="text-gray-900 dark:text-white">{{ $product->ean ?: '—' }} {{ $product->barcode ? '/ '.$product->barcode : '' }}</div>
                <div class="text-gray-500 dark:text-gray-400">Описание</div><div class="text-gray-900 dark:text-white">{{ $product->description ?: '—' }}</div>
            </div>

            <div class="mt-5 border-t border-gray-200 dark:border-gray-700 pt-4">
                <h3 class="font-medium text-gray-900 dark:text-white mb-2">Динамические атрибуты категории</h3>
                @if($attributes->isNotEmpty())
                    <div class="space-y-2">
                        @foreach($attributes as $value)
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                                <div class="text-gray-500 dark:text-gray-400">{{ $value->attribute->name }}</div>
                                <div class="text-gray-900 dark:text-white">{{ $value->value }}</div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">Характеристики не заполнены.</p>
                @endif
            </div>
        </section>
    </div>

    <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Информация о поставщиках</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="text-left py-2 pr-4">Поставщик</th>
                        <th class="text-left py-2 pr-4">Цена</th>
                        <th class="text-left py-2 pr-4">Остаток</th>
                        <th class="text-left py-2 pr-4">Условия поставки</th>
                        <th class="text-left py-2">Региональность</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($supplierRows as $row)
                        <tr class="border-b border-gray-100 dark:border-gray-700/60">
                            <td class="py-2 pr-4 text-gray-900 dark:text-white">{{ $row['name'] }}</td>
                            <td class="py-2 pr-4 text-gray-900 dark:text-white">{{ $row['price'] ? number_format((float) $row['price'], 2, ',', ' ') . ' ₽' : '—' }}</td>
                            <td class="py-2 pr-4 text-gray-900 dark:text-white">{{ $row['stock'] }}</td>
                            <td class="py-2 pr-4 text-gray-900 dark:text-white">{{ $row['conditions'] }}</td>
                            <td class="py-2 text-gray-900 dark:text-white">{{ $row['regions'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Остатки и складская информация</h2>
        @if($stocks->isNotEmpty())
            <div class="space-y-3">
                @foreach($stocks as $stock)
                    <div class="p-3 rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">{{ $stock->warehouse?->name ?? 'Склад' }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Регион: {{ $stock->warehouse?->region?->name ?? '—' }} ·
                                    Наличие: {{ $stock->available_quantity }} ·
                                    Мин. партия: {{ $product->min_order_quantity ?? '—' }}
                                </p>
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Обновлено: {{ optional($stock->stock_updated_at)->format('d.m.Y H:i') ?: '—' }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">Складские остатки не заполнены.</p>
        @endif
    </section>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Техническая документация</h2>
            @if($documents->isNotEmpty())
                <div class="space-y-2">
                    @foreach($documents as $document)
                        <div class="flex flex-wrap items-center justify-between gap-2 p-3 rounded-lg border border-gray-200 dark:border-gray-700">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $document->name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $document->typeLabel() }} · {{ $document->file_size_for_humans }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="{{ $document->url }}" target="_blank" rel="noopener"
                                   class="text-sm text-gray-600 dark:text-gray-300 hover:text-[#c3242a]">Просмотр</a>
                                <a href="{{ $document->url }}" download
                                   class="text-sm text-[#c3242a] hover:text-[#a01e24]">Скачать</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">Документы не прикреплены.</p>
            @endif
        </section>

        <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Логистические параметры</h2>
            <div class="space-y-2 text-sm">
                <p class="text-gray-900 dark:text-white"><span class="text-gray-500 dark:text-gray-400">Вес:</span> Не задан</p>
                <p class="text-gray-900 dark:text-white"><span class="text-gray-500 dark:text-gray-400">Габариты:</span> Не заданы</p>
                <p class="text-gray-900 dark:text-white"><span class="text-gray-500 dark:text-gray-400">Объём/кубатура:</span> Не задан</p>
                <p class="text-gray-900 dark:text-white"><span class="text-gray-500 dark:text-gray-400">Паллетные нормы:</span> Не заданы</p>
                <p class="text-gray-900 dark:text-white"><span class="text-gray-500 dark:text-gray-400">Требования к упаковке:</span> {{ $product->storage_conditions ?: 'Не заданы' }}</p>
                <p class="text-gray-900 dark:text-white"><span class="text-gray-500 dark:text-gray-400">Условия отгрузки:</span> {{ $product->transport_conditions ?: 'Не заданы' }}</p>
            </div>
        </section>
    </div>

    <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Аналоги товара</h2>
        @if($analogs->isNotEmpty())
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach($analogs as $analog)
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="aspect-[4/3] bg-gray-100 dark:bg-gray-700">
                            <img src="{{ $analog->primaryImage()?->url ?? asset('images/placeholder-product.svg') }}" alt="" class="w-full h-full object-cover" />
                        </div>
                        <div class="p-3">
                            <p class="text-sm font-medium text-gray-900 dark:text-white line-clamp-2">{{ $analog->name }}</p>
                            <p class="text-xs text-gray-500 mt-1">Артикул: {{ $analog->sku }}</p>
                            <p class="text-xs text-gray-500">{{ $analog->category?->name ?? 'Без категории' }}</p>
                            <a href="{{ route('admin.catalog.products.show', $analog) }}" class="inline-flex mt-2 text-sm text-[#c3242a] hover:text-[#a01e24]">Открыть карточку</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">Аналоги не назначены.</p>
        @endif
    </section>
</div>
@endsection
