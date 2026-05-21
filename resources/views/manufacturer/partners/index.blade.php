@extends('layouts.app')

@section('title', 'Каталог партнёров')
@section('heading', 'Каталог дистрибьюторов и компаний')

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

<div class="space-y-6" x-data="{ addModal: false, exclusiveModal: false, exclusiveDistributorId: null }">
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
                <select name="region_ids[]" multiple size="4"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                    @foreach($regions as $region)
                        <option value="{{ $region->id }}" @selected(in_array($region->id, array_map('intval', $selectedRegionIds)))>{{ $region->name }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">Ctrl/Cmd для нескольких регионов</p>
            </div>

            @if($isDistributors && $filterableCategories->isNotEmpty())
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Тип продукции</label>
                <select name="category_ids[]" multiple size="4"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                    @foreach($filterableCategories as $category)
                        <option value="{{ $category->id }}" @selected(in_array($category->id, array_map('intval', $selectedCategoryIds)))>{{ $category->name }}</option>
                    @endforeach
                </select>
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
                                    <div class="opacity-0 group-hover:opacity-100 transition-opacity inline-flex gap-2 justify-end">
                                        <a href="{{ route('manufacturer.partners.distributors.show', $item) }}" class="text-sm text-[#c3242a] hover:underline">Просмотреть</a>
                                        @if($permissions->canAddPartner(auth()->user()))
                                            @if($isPartner)
                                                <span class="text-sm text-gray-400">Уже в моих</span>
                                            @else
                                                <form method="POST" action="{{ route('manufacturer.partners.distributors.add', $item) }}" class="inline"
                                                    onsubmit="return confirm('Добавить дистрибьютора в мои?')">
                                                    @csrf
                                                    <button type="submit" class="text-sm text-[#c3242a] hover:underline">Добавить к моим</button>
                                                </form>
                                            @endif
                                        @endif
                                        @if($permissions->canAssignExclusive(auth()->user()) && ! $hasExclusive)
                                            <a href="{{ route('manufacturer.partners.distributors.show', $item) }}#exclusive" class="text-sm text-amber-600 hover:underline">Эксклюзив</a>
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
                                {{ $isDistributors ? 'Дистрибьюторы не найдены.' : 'Компании не найдены.' }}
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
</div>
@endsection
