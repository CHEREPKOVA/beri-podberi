@extends('layouts.app')

@section('title', 'Каталог')
@section('heading', 'Каталог')

@section('content')
@php
    $listingConfig = [
        'productsContainerId' => 'buyer-catalog-products-container',
        'productsFetchUrl' => route('buyer.catalog.products'),
        'baseCatalogUrl' => url('/buyer/catalog'),
    ];
@endphp
<div class="flex flex-col lg:flex-row gap-6"
    x-data="buyerCatalogApp(@js($selectedCategory?->slug), @js($catalogTreeOpenSlugs ?? []), @js($listingConfig))"
    @load-category.window="loadCategory($event.detail.slug)"
    @catalog-apply-filters.window="applyCatalogFilters()"
    @catalog-reset-filters.window="resetCatalogFilters()">
    <aside class="lg:w-72 shrink-0">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-gray-900 dark:text-white">Категории</h2>
                    @if($companyRegionName)
                        <p class="text-xs text-gray-500 mt-1">Регион: {{ $companyRegionName }}</p>
                    @endif
                </div>
                <button type="button" @click="collapseAll()"
                    class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-400 shrink-0">
                    Свернуть всё
                </button>
            </div>
            <nav class="p-2 max-h-[calc(100vh-14rem)] overflow-y-auto">
                <a href="{{ route('buyer.catalog.index') }}"
                    @click.prevent="loadCategory(null)"
                    :class="categoryLinkClass(null)">
                    Все категории
                </a>
                @foreach($categoryTree as $root)
                    @include('manufacturer.catalog._tree_node', ['node' => $root, 'level' => 0, 'routeName' => 'buyer.catalog.index'])
                @endforeach
            </nav>
        </div>
    </aside>

    <main class="flex-1 min-w-0">
        @include('catalog._search_bar', ['searchQuery' => $searchQuery ?? ''])
        <div id="buyer-catalog-products-container">
            @include('catalog._products', [
                'products' => $products,
                'selectedCategory' => $selectedCategory ?? null,
                'selectedCategoryId' => $selectedCategoryId,
                'filterableAttributes' => $filterableAttributes ?? collect(),
                'appliedFilters' => $appliedFilters ?? [],
                'manufacturerProfileId' => $manufacturerProfileId ?? null,
                'companyRegionName' => $companyRegionName ?? null,
                'searchQuery' => $searchQuery ?? '',
                'catalogIndexRoute' => 'buyer.catalog.index',
                'catalogShowRoute' => 'buyer.catalog.show',
            ])
        </div>
    </main>
</div>

@push('scripts')
<script>
@include('manufacturer.catalog._catalog_tree_alpine')

function buyerCatalogApp(initialCategorySlug, initialOpenSlugs, listingConfig) {
    return {
        ...catalogTreeMixin(initialCategorySlug, initialOpenSlugs),
        ...catalogListingMixin(listingConfig),
    };
}
</script>
@endpush
@endsection
