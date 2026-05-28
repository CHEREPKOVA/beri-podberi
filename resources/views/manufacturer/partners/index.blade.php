@extends('layouts.app')

@section('title', 'Дистрибьюторы')
@section('heading', 'Дистрибьюторы')

@section('content')
@php
    $isDistributors = $catalogType === \App\Services\ManufacturerPartnerCatalogService::CATALOG_DISTRIBUTORS;
    $selectedCategoryIds = request()->input('category_ids', $defaultCategoryIds ?? []);
    if (! is_array($selectedCategoryIds)) {
        $selectedCategoryIds = [$selectedCategoryIds];
    }
    $selectedRegionIds = request()->input('region_ids', []);
    if (! is_array($selectedRegionIds)) {
        $selectedRegionIds = [$selectedRegionIds];
    }
@endphp

<div class="space-y-6" x-data="{ addModalOpen: false, addAction: '', addDistributorName: '' }">
    @if(session('success'))
    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 px-4 py-3 rounded-lg">
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg">
        {{ session('error') }}
    </div>
    @endif
    @if(session('info'))
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-300 px-4 py-3 rounded-lg">
        {{ session('info') }}
    </div>
    @endif

    {{-- Переключатель типа каталога --}}
    <div class="inline-flex rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-1">
        <a href="{{ route('manufacturer.partners.index', array_merge(request()->except('type', 'page'), ['type' => 'distributors'])) }}"
           class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $isDistributors ? 'bg-[#c3242a] text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
            Дистрибьютеры
        </a>
        <a href="{{ route('manufacturer.partners.index', array_merge(request()->except('type', 'page'), ['type' => 'companies'])) }}"
           class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ ! $isDistributors ? 'bg-[#c3242a] text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
            Компании
        </a>
    </div>

    {{-- Фильтры --}}
    <form method="GET" action="{{ route('manufacturer.partners.index') }}" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 space-y-4">
        <input type="hidden" name="type" value="{{ $catalogType }}">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-1">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Поиск</label>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Название, ИНН…"
                    class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#c3242a]"
                    oninput="clearTimeout(window._partnerSearchTimer); window._partnerSearchTimer = setTimeout(() => this.form.requestSubmit(), 400)" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Регион</label>
                <x-multi-select-filter
                    name="region_ids[]"
                    :options="$regions"
                    :selected="$selectedRegionIds"
                    placeholder="Все регионы"
                    :searchable="true"
                />
            </div>

            @if($isDistributors && $filterableCategories->isNotEmpty())
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Тип продукции</label>
                <x-multi-select-filter
                    name="category_ids[]"
                    :options="$filterableCategories"
                    :selected="$selectedCategoryIds"
                    placeholder="Все типы"
                />
            </div>
            @endif
        </div>

        <div class="flex flex-wrap gap-3 items-center">
            <button type="submit" class="px-4 py-2 bg-[#c3242a] text-white text-sm rounded-lg hover:bg-[#a01e24]">Применить</button>
            <a href="{{ route('manufacturer.partners.index', ['type' => $catalogType, 'categories_reset' => 1]) }}"
               class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm rounded-lg hover:border-[#c3242a]">
                Сбросить фильтры
            </a>
        </div>
    </form>

    {{-- Таблица --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        @if($isDistributors)
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'name', 'direction' => ($sort === 'name' && $direction === 'asc') ? 'desc' : 'asc']) }}" class="hover:text-[#c3242a]">Название</a>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Регион</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Типы продукции</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'orders', 'direction' => ($sort === 'orders' && $direction === 'asc') ? 'desc' : 'asc']) }}" class="hover:text-[#c3242a]">Заказы</a>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'registered_at', 'direction' => ($sort === 'registered_at' && $direction === 'asc') ? 'desc' : 'asc']) }}" class="hover:text-[#c3242a]">Регистрация</a>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Статус</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Действия</th>
                        @else
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'name', 'direction' => ($sort === 'name' && $direction === 'asc') ? 'desc' : 'asc']) }}" class="hover:text-[#c3242a]">Название</a>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Регион</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Тип деятельности</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'orders', 'direction' => ($sort === 'orders' && $direction === 'asc') ? 'desc' : 'asc']) }}" class="hover:text-[#c3242a]">Заказы</a>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'registered_at', 'direction' => ($sort === 'registered_at' && $direction === 'asc') ? 'desc' : 'asc']) }}" class="hover:text-[#c3242a]">Регистрация</a>
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase"></th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($items as $item)
                        @if($isDistributors)
                            @php
                                $status = $item->partnership_status ?? 'not_connected';
                                $statusLabel = $catalogService->cooperationStatusLabel($status);
                                $isPartner = in_array($status, ['connected', 'exclusive'], true);
                                $hasExclusive = $status === 'exclusive';
                            @endphp
                            <tr class="group hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('manufacturer.partners.distributors.show', $item) }}" class="font-medium text-gray-900 dark:text-white hover:text-[#c3242a]">
                                        {{ $item->displayName() }}
                                    </a>
                                    @if($item->inn)
                                        <span class="block text-xs text-gray-500">ИНН {{ $item->inn }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $item->primaryRegion()?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $item->productCategories->pluck('name')->join(', ') ?: '—' }}
                                </td>
                                <td class="px-4 py-3 text-sm">{{ $item->orders_count ?? 0 }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $item->created_at?->format('d.m.Y') }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2 py-0.5 text-xs rounded-full
                                        @if($status === 'exclusive') bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300
                                        @elseif($status === 'connected') bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300
                                        @else bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 @endif">
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <a href="{{ route('manufacturer.partners.distributors.show', $item) }}"
                                           class="p-1.5 text-gray-400 hover:text-[#c3242a] rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
                                           title="Просмотреть">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </a>
                                        @if($permissions->canAddPartner(auth()->user()))
                                            @if($isPartner)
                                                <span class="p-1.5 text-green-500 rounded-lg cursor-default" title="Уже в моих">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                </span>
                                            @else
                                                <button type="button"
                                                    @click="addAction = '{{ route('manufacturer.partners.distributors.add', $item) }}'; addDistributorName = {{ json_encode($item->displayName()) }}; addModalOpen = true"
                                                    class="p-1.5 text-gray-400 hover:text-[#c3242a] rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
                                                    title="Добавить к моим">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                                                    </svg>
                                                </button>
                                            @endif
                                        @endif
                                        @if($permissions->canAssignExclusive(auth()->user()) && ! $hasExclusive)
                                            <a href="{{ route('manufacturer.partners.distributors.show', $item) }}#exclusive"
                                               class="p-1.5 text-gray-400 hover:text-amber-600 rounded-lg hover:bg-amber-50 dark:hover:bg-amber-900/20"
                                               title="Эксклюзив">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                                </svg>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @else
                            <tr class="group hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('manufacturer.partners.companies.show', $item) }}" class="font-medium text-gray-900 dark:text-white hover:text-[#c3242a]">
                                        {{ $item->displayName() }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    @php
                                        $regionNames = $item->deliveryAddresses->map(fn ($a) => $a->region?->name)->filter()->unique();
                                    @endphp
                                    {{ $regionNames->isNotEmpty() ? $regionNames->join(', ') : '—' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $item->activity_type ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm">{{ $item->orders_count ?? 0 }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $item->created_at?->format('d.m.Y') }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('manufacturer.partners.companies.show', $item) }}" class="text-sm text-[#c3242a] opacity-0 group-hover:opacity-100 hover:underline">Просмотреть</a>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="{{ $isDistributors ? 7 : 6 }}" class="px-4 py-10 text-center text-sm text-gray-500">
                                @if($isDistributors)
                                    Дистрибьюторы не найдены. В каталог попадают только партнёры с указанным регионом и типом продукции.
                                @else
                                    Компании не найдены.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($items->hasPages())
        <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
            {{ $items->links('vendor.pagination.tailwind') }}
        </div>
        @endif
    </div>
    <div
        x-show="addModalOpen"
        x-cloak
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
        @keydown.escape.window="addModalOpen = false"
    >
        <div
            x-show="addModalOpen"
            x-transition
            class="w-full max-w-md rounded-2xl bg-white dark:bg-gray-800 shadow-xl border border-gray-200 dark:border-gray-700"
            @click.away="addModalOpen = false"
        >
            <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Добавить дистрибьютора в мои?</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="addDistributorName"></p>
            </div>

            <form method="POST" :action="addAction" class="px-6 py-4 flex items-center justify-end gap-3">
                @csrf
                <button type="button"
                    @click="addModalOpen = false"
                    class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                    Отменить
                </button>
                <button type="submit"
                    class="px-4 py-2 text-sm font-medium text-white bg-[#c3242a] rounded-lg hover:bg-[#a01e24]">
                    Добавить
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
