@extends('layouts.app')

@section('title', $product->name)
@section('heading', 'Карточка товара')

@section('content')
<div x-data="{ activeTab: '{{ $tab }}', showPriceModal: false, priceType: 'retail' }" class="space-y-6">
    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">{{ session('error') }}</div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-4">
        <a href="{{ route('distributor.products.index') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">
            <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Назад к номенклатуре
        </a>
        <div class="flex flex-wrap gap-2">
            @if($product->status !== \App\Models\DistributorProduct::STATUS_ACTIVE)
            <form method="POST" action="{{ route('distributor.products.publish', $product) }}">@csrf
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">Опубликовать товар</button>
            </form>
            @else
            <form method="POST" action="{{ route('distributor.products.hide', $product) }}">@csrf
                <button type="submit" class="px-4 py-2 bg-gray-600 text-white rounded-lg text-sm font-medium hover:bg-gray-700">Скрыть товар</button>
            </form>
            @endif
            @if($product->status !== \App\Models\DistributorProduct::STATUS_ARCHIVE)
            <form method="POST" action="{{ route('distributor.products.archive', $product) }}" onsubmit="return confirm('Перевести товар в архив?')">@csrf
                <button type="submit" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50">В архив</button>
            </form>
            @endif
        </div>
    </div>

    @if($managedBy1c)
    <div class="px-4 py-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-800">
        Управляется 1С — часть полей недоступна для ручного редактирования.
    </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="flex flex-col md:flex-row gap-6 p-6 border-b border-gray-200 dark:border-gray-700">
            <div class="shrink-0">
                @if($url = $product->primaryImageUrl())
                <img src="{{ $url }}" alt="" class="w-40 h-40 object-cover rounded-xl border border-gray-200" />
                @else
                <div class="w-40 h-40 bg-gray-100 rounded-xl flex items-center justify-center text-gray-400">Нет фото</div>
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ $product->name }}</h2>
                <p class="text-sm text-gray-500 mt-1">{{ $product->manufacturerName() }}</p>
                <div class="flex flex-wrap gap-2 mt-3">
                    <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium {{ $product->statusBadgeClass() }}">{{ $product->statusLabel() }}</span>
                    <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">{{ $product->syncSourceLabel() }}</span>
                    @if($product->manufacturer_archived)
                    <span class="text-xs text-amber-700 bg-amber-50 px-2 py-1 rounded">Архив производителя</span>
                    @endif
                </div>
                <dl class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-4 text-sm">
                    <div><dt class="text-gray-500">Артикул производителя</dt><dd class="font-mono">{{ $product->manufacturer_sku ?: '—' }}</dd></div>
                    <div><dt class="text-gray-500">Внутренний артикул</dt><dd class="font-mono">{{ $product->internal_sku }}</dd></div>
                    <div><dt class="text-gray-500">Штрихкод</dt><dd>{{ $product->barcode ?: '—' }}</dd></div>
                </dl>
            </div>
        </div>

        <nav class="flex border-b border-gray-200 dark:border-gray-700 px-6 overflow-x-auto">
            @foreach(['info' => 'Основное', 'prices' => 'Цены', 'stocks' => 'Остатки', 'documents' => 'Документы', 'log' => 'История'] as $key => $label)
            <button type="button" @click="activeTab = '{{ $key }}'"
                :class="activeTab === '{{ $key }}' ? 'border-[#c3242a] text-[#c3242a]' : 'border-transparent text-gray-500 hover:text-gray-700'"
                class="px-4 py-3 text-sm font-medium border-b-2 whitespace-nowrap">{{ $label }}</button>
            @endforeach
        </nav>

        <div class="p-6">
            {{-- Основная информация --}}
            <div x-show="activeTab === 'info'" x-cloak>
                <form method="POST" action="{{ route('distributor.products.update', $product) }}" class="space-y-4 max-w-3xl">
                    @csrf @method('PUT')
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @unless($product->managed_by_1c && $product->isSyncedFrom1c())
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Наименование</label>
                            <input type="text" name="name" value="{{ old('name', $product->name) }}" required class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700" />
                        </div>
                        @endunless
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Внутренний артикул</label>
                            <input type="text" name="internal_sku" value="{{ old('internal_sku', $product->internal_sku) }}" required class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700" />
                        </div>
                        @unless($product->managed_by_1c && $product->isSyncedFrom1c())
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Артикул производителя</label>
                            <input type="text" name="manufacturer_sku" value="{{ old('manufacturer_sku', $product->manufacturer_sku) }}" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Бренд</label>
                            <input type="text" name="brand" value="{{ old('brand', $product->brand) }}" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Штрихкод</label>
                            <input type="text" name="barcode" value="{{ old('barcode', $product->barcode) }}" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Страна производства</label>
                            <input type="text" name="country_of_origin" value="{{ old('country_of_origin', $product->country_of_origin) }}" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Кол-во в упаковке</label>
                            <input type="number" name="pack_quantity" value="{{ old('pack_quantity', $product->pack_quantity) }}" min="1" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Кратность заказа</label>
                            <input type="number" name="min_order_quantity" value="{{ old('min_order_quantity', $product->min_order_quantity) }}" min="1" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700" />
                        </div>
                        @endunless
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Краткое описание</label>
                        <textarea name="short_description" rows="2" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">{{ old('short_description', $product->short_description) }}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Описание</label>
                        <textarea name="description" rows="4" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">{{ old('description', $product->description) }}</textarea>
                    </div>

                    @if($product->sourceProduct && $product->sourceProduct->attributeValues->isNotEmpty())
                    <div class="pt-4 border-t border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Характеристики (из каталога производителя)</h3>
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                            @foreach($product->sourceProduct->attributeValues as $av)
                            <div><dt class="text-gray-500">{{ $av->attribute?->name }}</dt><dd>{{ $av->value }}</dd></div>
                            @endforeach
                        </dl>
                    </div>
                    @endif

                    <button type="submit" class="px-6 py-2 bg-[#c3242a] text-white rounded-lg text-sm font-medium hover:bg-[#a01e24]">Сохранить</button>
                </form>
            </div>

            {{-- Цены --}}
            <div x-show="activeTab === 'prices'" x-cloak>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-4xl mb-8">
                    <div class="p-4 border border-gray-200 rounded-lg">
                        <p class="text-sm text-gray-500">Закупочная цена</p>
                        <p class="text-2xl font-semibold mt-1">{{ $product->purchase_price ? number_format($product->purchase_price, 2, ',', ' ') . ' ₽' : '—' }}</p>
                        <button type="button" @click="showPriceModal = true; priceType = 'purchase'" class="mt-3 text-sm text-[#c3242a] hover:underline">Изменить цену</button>
                    </div>
                    <div class="p-4 border border-gray-200 rounded-lg">
                        <p class="text-sm text-gray-500">Отпускная цена (для клиентов)</p>
                        <p class="text-2xl font-semibold mt-1">{{ $product->retail_price ? number_format($product->retail_price, 2, ',', ' ') . ' ₽' : '—' }}</p>
                        <p class="text-xs text-gray-400 mt-1">Обновлено: {{ $product->price_updated_at?->format('d.m.Y H:i') ?? '—' }}</p>
                        <button type="button" @click="showPriceModal = true; priceType = 'retail'" class="mt-3 text-sm text-[#c3242a] hover:underline">Изменить цену</button>
                    </div>
                </div>

                <h3 class="text-sm font-semibold text-gray-700 mb-3">История изменений цен</h3>
                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50"><tr>
                            <th class="px-4 py-2 text-left">Дата</th>
                            <th class="px-4 py-2 text-left">Тип</th>
                            <th class="px-4 py-2 text-left">Было</th>
                            <th class="px-4 py-2 text-left">Стало</th>
                            <th class="px-4 py-2 text-left">Комментарий</th>
                            <th class="px-4 py-2 text-left">Кто</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($product->priceHistories as $h)
                            <tr>
                                <td class="px-4 py-2">{{ $h->created_at->format('d.m.Y H:i') }}</td>
                                <td class="px-4 py-2">{{ $h->typeLabel() }}</td>
                                <td class="px-4 py-2">{{ $h->old_price !== null ? number_format($h->old_price, 2, ',', ' ') . ' ₽' : '—' }}</td>
                                <td class="px-4 py-2 font-medium">{{ number_format($h->new_price, 2, ',', ' ') }} ₽</td>
                                <td class="px-4 py-2 text-gray-500">{{ $h->comment ?: '—' }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $h->changedByUser?->name ?? '—' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">История пуста</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Остатки --}}
            <div x-show="activeTab === 'stocks'" x-cloak>
                @if($stockEditingDisabled)
                <p class="text-sm text-gray-600 mb-4">Остатки обновляются автоматически из 1С.</p>
                @endif
                <form method="POST" action="{{ route('distributor.products.stocks.update', $product) }}">
                    @csrf
                    <div class="overflow-x-auto border border-gray-200 rounded-lg mb-4">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50"><tr>
                                <th class="px-4 py-2 text-left">Склад</th>
                                <th class="px-4 py-2 text-left">Адрес</th>
                                <th class="px-4 py-2 text-left">Статус</th>
                                <th class="px-4 py-2 text-left">Остаток</th>
                                <th class="px-4 py-2 text-left">Обновлён</th>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($warehouses as $wh)
                                @php $stock = $product->stocks->firstWhere('distributor_warehouse_id', $wh->id); @endphp
                                <tr>
                                    <td class="px-4 py-2 font-medium">{{ $wh->name }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $wh->address ?: '—' }}</td>
                                    <td class="px-4 py-2">
                                        <span class="text-xs px-2 py-0.5 rounded {{ $wh->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                            {{ $wh->is_active ? 'Активный' : 'Архивный' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="hidden" name="stocks[{{ $loop->index }}][warehouse_id]" value="{{ $wh->id }}" />
                                        <input type="number" name="stocks[{{ $loop->index }}][quantity]" value="{{ $stock?->quantity ?? 0 }}" min="0"
                                            {{ $stockEditingDisabled ? 'readonly' : '' }}
                                            class="w-24 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 {{ $stockEditingDisabled ? 'bg-gray-100' : '' }}" />
                                    </td>
                                    <td class="px-4 py-2 text-gray-500 text-xs">{{ $stock?->stock_updated_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @unless($stockEditingDisabled)
                    <button type="submit" class="px-6 py-2 bg-[#c3242a] text-white rounded-lg text-sm font-medium">Сохранить остатки</button>
                    @endunless
                </form>
            </div>

            {{-- Документы --}}
            <div x-show="activeTab === 'documents'" x-cloak>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div>
                        <h3 class="text-sm font-semibold mb-3">Документы дистрибьютора</h3>
                        <ul class="space-y-2 mb-6">
                            @forelse($product->documents as $doc)
                            <li class="flex items-center justify-between p-3 border border-gray-200 rounded-lg text-sm">
                                <span>{{ $doc->name }} <span class="text-gray-400">({{ $doc->typeLabel() }})</span></span>
                                <div class="flex gap-2">
                                    <a href="{{ $doc->url }}" target="_blank" class="text-[#c3242a] hover:underline">Скачать</a>
                                    @if($product->status !== \App\Models\DistributorProduct::STATUS_ARCHIVE)
                                    <form method="POST" action="{{ route('distributor.products.documents.delete', [$product, $doc]) }}" onsubmit="return confirm('Удалить?')">@csrf @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline">Удалить</button>
                                    </form>
                                    @endif
                                </div>
                            </li>
                            @empty
                            <li class="text-gray-500 text-sm">Нет документов</li>
                            @endforelse
                        </ul>
                        @if($product->status !== \App\Models\DistributorProduct::STATUS_ARCHIVE)
                        <form method="POST" action="{{ route('distributor.products.documents.store', $product) }}" enctype="multipart/form-data" class="space-y-3 p-4 bg-gray-50 rounded-lg">
                            @csrf
                            <input type="text" name="name" placeholder="Название" required class="w-full rounded-lg border-gray-300 text-sm" />
                            <select name="type" class="w-full rounded-lg border-gray-300 text-sm">
                                @foreach(\App\Models\DistributorProductDocument::typeLabels() as $k => $l)
                                <option value="{{ $k }}">{{ $l }}</option>
                                @endforeach
                            </select>
                            <input type="file" name="file" required class="w-full text-sm" />
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_internal" value="1" /> Внутренний файл</label>
                            <button type="submit" class="px-4 py-2 bg-[#c3242a] text-white rounded-lg text-sm">Добавить</button>
                        </form>
                        @endif
                    </div>
                    @if($product->sourceProduct && $product->sourceProduct->documents->isNotEmpty())
                    <div>
                        <h3 class="text-sm font-semibold mb-3">Файлы производителя</h3>
                        <ul class="space-y-2">
                            @foreach($product->sourceProduct->documents as $doc)
                            <li class="p-3 border border-gray-200 rounded-lg text-sm flex justify-between">
                                <span>{{ $doc->name }}</span>
                                <a href="{{ $doc->url }}" target="_blank" class="text-[#c3242a]">Скачать</a>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Лог --}}
            <div x-show="activeTab === 'log'" x-cloak>
                <ul class="space-y-3">
                    @forelse($product->changeLogs as $log)
                    <li class="p-4 border border-gray-200 rounded-lg text-sm">
                        <div class="flex justify-between gap-4">
                            <span class="font-medium text-gray-900">{{ $log->description ?: $log->action }}</span>
                            <span class="text-gray-400 shrink-0">{{ $log->created_at->format('d.m.Y H:i') }}</span>
                        </div>
                        <p class="text-gray-500 mt-1">{{ $log->performedByUser?->name ?? 'Система' }}</p>
                    </li>
                    @empty
                    <li class="text-gray-500 text-center py-8">Записей пока нет</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    {{-- Модалка изменения цены --}}
    <div x-show="showPriceModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @keydown.escape.window="showPriceModal = false">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-md w-full p-6" @click.outside="showPriceModal = false">
            <h3 class="text-lg font-semibold mb-4">Изменить цену</h3>
            <form method="POST" action="{{ route('distributor.products.price.update', $product) }}">
                @csrf
                <input type="hidden" name="price_type" :value="priceType" />
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Новая цена, ₽</label>
                        <input type="number" name="new_price" step="0.01" min="0" required class="w-full rounded-lg border-gray-300" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Комментарий</label>
                        <textarea name="comment" rows="2" class="w-full rounded-lg border-gray-300"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Дата вступления в силу</label>
                        <input type="datetime-local" name="effective_at" class="w-full rounded-lg border-gray-300" />
                    </div>
                </div>
                <div class="flex gap-2 mt-6">
                    <button type="submit" class="flex-1 py-2 bg-[#c3242a] text-white rounded-lg text-sm font-medium">Сохранить</button>
                    <button type="button" @click="showPriceModal = false" class="flex-1 py-2 border border-gray-300 rounded-lg text-sm">Отменить</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
