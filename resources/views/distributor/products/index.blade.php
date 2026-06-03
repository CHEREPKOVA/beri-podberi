@extends('layouts.app')

@section('title', 'Номенклатура')
@section('heading', 'Номенклатура')

@section('content')
@php
    $sort = request('sort', 'updated_at');
    $dir = request('dir', 'desc') === 'asc' ? 'asc' : 'desc';
    $sortUrl = function (string $column) use ($sort, $dir) {
        $nextDir = ($sort === $column && $dir === 'asc') ? 'desc' : 'asc';

        return request()->fullUrlWithQuery(['sort' => $column, 'dir' => $nextDir]);
    };
    $sortIcon = function (string $column) use ($sort, $dir) {
        if ($sort !== $column) {
            return '<span class="text-gray-300 dark:text-gray-600 ml-0.5" aria-hidden="true">↕</span>';
        }

        return $dir === 'asc'
            ? '<span class="text-[#c3242a] ml-0.5" aria-hidden="true">↑</span>'
            : '<span class="text-[#c3242a] ml-0.5" aria-hidden="true">↓</span>';
    };
@endphp
<div class="space-y-6">
    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">{{ session('error') }}</div>
    @endif
    @if(session('warning'))
    <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded-lg">
        {{ session('warning') }}
        @if(session('import_errors'))
        <ul class="mt-2 text-sm list-disc list-inside">
            @foreach(session('import_errors') as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
        @endif
    </div>
    @endif

    @if($managedBy1c)
    <div class="flex items-center gap-2 px-4 py-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg text-sm text-blue-800 dark:text-blue-200">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Часть данных управляется из 1С. Ручное редактирование остатков может быть ограничено.
    </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-4">
                <form method="GET" class="flex flex-wrap items-center gap-3 flex-1">
                    @if(request('sort'))
                    <input type="hidden" name="sort" value="{{ request('sort') }}" />
                    @endif
                    @if(request('dir'))
                    <input type="hidden" name="dir" value="{{ request('dir') }}" />
                    @endif
                    <div class="relative">
                        <input type="text" name="search" value="{{ request('search') }}"
                            placeholder="Название, артикул, штрихкод, бренд..."
                            class="w-72 pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
                        <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>

                    <select name="category" class="pl-3 pr-8 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                        <option value="">Все категории</option>
                        @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>

                    <select name="brand" class="pl-3 pr-8 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm max-w-[160px]">
                        <option value="">Все бренды</option>
                        @foreach($brands as $brandName)
                        <option value="{{ $brandName }}" @selected(request('brand') === $brandName)>{{ $brandName }}</option>
                        @endforeach
                    </select>

                    <select name="status" class="pl-3 pr-8 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                        <option value="">Любой статус</option>
                        @foreach(\App\Models\DistributorProduct::statusLabels() as $key => $label)
                        <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>

                    <select name="has_stock" class="pl-3 pr-8 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                        <option value="">Наличие</option>
                        <option value="yes" @selected(request('has_stock') === 'yes')>В наличии</option>
                        <option value="no" @selected(request('has_stock') === 'no')>Нет на складе</option>
                    </select>

                    <input type="number" name="price_min" value="{{ request('price_min') }}" placeholder="Цена от" step="0.01"
                        class="w-24 py-2 px-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm" />
                    <input type="number" name="price_max" value="{{ request('price_max') }}" placeholder="до" step="0.01"
                        class="w-24 py-2 px-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm" />

                    <input type="date" name="updated_from" value="{{ request('updated_from') }}" title="Обновлён с"
                        class="py-2 px-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm" />
                    <input type="date" name="updated_to" value="{{ request('updated_to') }}" title="по"
                        class="py-2 px-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm" />

                    <button type="submit" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 text-sm font-medium">Применить</button>
                    @if(request()->hasAny(['search','category','brand','status','has_stock','price_min','price_max','updated_from','updated_to','sort','dir']))
                    <a href="{{ route('distributor.products.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Сбросить фильтры</a>
                    @endif
                </form>

                <a href="{{ route('distributor.products.import') }}"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 text-sm font-medium shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Импорт CSV
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="w-16 px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Фото</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ $sortUrl('name') }}" class="inline-flex items-center hover:text-gray-700 dark:hover:text-gray-200" onclick="event.stopPropagation()">
                                Наименование {!! $sortIcon('name') !!}
                            </a>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ $sortUrl('internal_sku') }}" class="inline-flex items-center hover:text-gray-700 dark:hover:text-gray-200" onclick="event.stopPropagation()">
                                Артикулы {!! $sortIcon('internal_sku') !!}
                            </a>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ $sortUrl('brand') }}" class="inline-flex items-center hover:text-gray-700 dark:hover:text-gray-200" onclick="event.stopPropagation()">
                                Бренд {!! $sortIcon('brand') !!}
                            </a>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ $sortUrl('category') }}" class="inline-flex items-center hover:text-gray-700 dark:hover:text-gray-200" onclick="event.stopPropagation()">
                                Категория {!! $sortIcon('category') !!}
                            </a>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ $sortUrl('purchase_price') }}" class="inline-flex items-center hover:text-gray-700 dark:hover:text-gray-200" onclick="event.stopPropagation()">
                                Закуп. {!! $sortIcon('purchase_price') !!}
                            </a>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ $sortUrl('retail_price') }}" class="inline-flex items-center hover:text-gray-700 dark:hover:text-gray-200" onclick="event.stopPropagation()">
                                Отпуск. {!! $sortIcon('retail_price') !!}
                            </a>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ $sortUrl('stock') }}" class="inline-flex items-center hover:text-gray-700 dark:hover:text-gray-200" onclick="event.stopPropagation()">
                                Остаток {!! $sortIcon('stock') !!}
                            </a>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ $sortUrl('status') }}" class="inline-flex items-center hover:text-gray-700 dark:hover:text-gray-200" onclick="event.stopPropagation()">
                                Статус {!! $sortIcon('status') !!}
                            </a>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ $sortUrl('updated_at') }}" class="inline-flex items-center hover:text-gray-700 dark:hover:text-gray-200" onclick="event.stopPropagation()">
                                Обновлён {!! $sortIcon('updated_at') !!}
                            </a>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ $sortUrl('sync_source') }}" class="inline-flex items-center hover:text-gray-700 dark:hover:text-gray-200" onclick="event.stopPropagation()">
                                Источник {!! $sortIcon('sync_source') !!}
                            </a>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($products as $product)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer {{ $product->isHidden() ? 'opacity-50' : '' }}"
                        onclick="window.location='{{ route('distributor.products.show', $product) }}'">
                        <td class="px-4 py-3">
                            @if($url = $product->primaryImageUrl())
                            <img src="{{ $url }}" alt="" class="w-12 h-12 object-cover rounded-lg" />
                            @else
                            <div class="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $product->name }}</span>
                            <div class="flex flex-wrap gap-1 mt-1">
                                @unless($product->hasStock())
                                <span class="text-xs text-orange-600 bg-orange-50 px-2 py-0.5 rounded">Нет на складе</span>
                                @endunless
                                @if($product->sync_source === '1c' && $product->synced_at)
                                <span class="text-xs text-blue-600 bg-blue-50 px-2 py-0.5 rounded">Обновлено 1С</span>
                                @endif
                                @if($product->manufacturer_archived)
                                <span class="text-xs text-amber-700 bg-amber-50 px-2 py-0.5 rounded">Архив производителя</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400">
                            <div>Произв.: {{ $product->manufacturer_sku ?: '—' }}</div>
                            <div class="font-mono mt-0.5">Внутр.: {{ $product->internal_sku }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $product->manufacturerName() ?: '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $product->category?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm">{{ $product->purchase_price ? number_format($product->purchase_price, 2, ',', ' ') . ' ₽' : '—' }}</td>
                        <td class="px-4 py-3 text-sm font-medium">{{ $product->retail_price ? number_format($product->retail_price, 2, ',', ' ') . ' ₽' : '—' }}</td>
                        <td class="px-4 py-3 text-sm {{ $product->hasStock() ? 'text-green-600' : 'text-red-600' }}">{{ $product->total_stock }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium {{ $product->statusBadgeClass() }}">{{ $product->statusLabel() }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $product->updated_at->format('d.m.Y') }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ $product->syncSourceLabel() }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="11" class="px-4 py-12 text-center text-gray-500">
                            Товары не найдены. Добавьте позиции через импорт или синхронизацию с каталогом производителя.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($products->hasPages())
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">{{ $products->links() }}</div>
        @endif
    </div>
</div>
@endsection
