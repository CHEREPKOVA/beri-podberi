@extends('layouts.app')

@section('title', 'Каталог товаров')
@section('heading', 'Каталог товаров')

@section('content')
@php
    $listingConfig = [
        'productsContainerId' => 'catalog-products-container',
        'productsFetchUrl' => route('manufacturer.catalog.products'),
        'baseCatalogUrl' => url('/manufacturer/catalog'),
        'catalogSearchSuggestUrl' => $catalogSearchSuggestUrl ?? route('manufacturer.catalog.search.suggest'),
        'searchMinQueryLength' => $searchMinQueryLength ?? 2,
    ];
@endphp
<div class="flex flex-col lg:flex-row gap-6"
    x-data="catalogApp(@js($selectedCategory?->slug), @js($catalogTreeOpenSlugs ?? []), @js($listingConfig))"
    @load-category.window="loadCategory($event.detail.slug)"
    @catalog-apply-filters.window="applyCatalogFilters()"
    @catalog-apply-filters-debounced.window="applyCatalogFiltersDebounced()"
    @catalog-reset-filters.window="resetCatalogFilters()">
    <aside class="lg:w-72 shrink-0">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h2 class="font-semibold text-gray-900 dark:text-white">Категории</h2>
                <button type="button" @click="collapseAll()"
                    class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-400">
                    Свернуть всё
                </button>
            </div>
            <nav class="p-2 max-h-[calc(100vh-12rem)] overflow-y-auto">
                <a href="{{ route('manufacturer.catalog.index') }}"
                    @click.prevent="loadCategory(null)"
                    :class="categoryLinkClass(null)">
                    Все категории
                </a>
                @foreach($categoryTree as $root)
                    @include('manufacturer.catalog._tree_node', ['node' => $root, 'level' => 0])
                @endforeach
            </nav>
        </div>
    </aside>

    <main class="flex-1 min-w-0">
        @include('catalog._search_bar', [
            'searchQuery' => $searchQuery ?? '',
            'selectedCategory' => $selectedCategory ?? null,
            'listingParams' => $listingParams ?? null,
        ])
        <div id="catalog-products-container">
            @include('catalog._products', [
                'products' => $products,
                'selectedCategory' => $selectedCategory ?? null,
                'selectedCategoryId' => $selectedCategoryId,
                'filterableAttributes' => $filterableAttributes ?? collect(),
                'appliedFilters' => $appliedFilters ?? [],
                'listingParams' => $listingParams ?? null,
                'visibleStructuralFilters' => $visibleStructuralFilters ?? [],
                'filterDistributors' => $filterDistributors ?? collect(),
                'filterManufacturers' => $filterManufacturers ?? collect(),
                'priceBounds' => $priceBounds ?? null,
                'manufacturerProfileId' => $manufacturerProfileId ?? null,
                'searchQuery' => $searchQuery ?? '',
                'catalogIndexRoute' => 'manufacturer.catalog.index',
                'catalogShowRoute' => 'manufacturer.catalog.show',
                'showNomenclatureLink' => true,
            ])
        </div>
    </main>
</div>

@push('scripts')
<script>
@include('manufacturer.catalog._catalog_tree_alpine')

function catalogApp(initialCategorySlug, initialOpenSlugs, listingConfig) {
    return {
        ...catalogTreeMixin(initialCategorySlug, initialOpenSlugs),
        ...catalogListingMixin(listingConfig),
        ...catalogSearchSuggestMixin(listingConfig),
        ...catalogFiltersMixin(),
    };
}
</script>
@endpush
@endsection
