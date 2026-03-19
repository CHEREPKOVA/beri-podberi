@extends('layouts.app')

@section('title', 'Каталог товаров')
@section('heading', 'Каталог товаров')

@section('content')
<div class="flex flex-col lg:flex-row gap-6" x-data="catalogApp({{ $selectedCategoryId ?? 'null' }}, {{ json_encode($selectedCategory?->slug) }})" @load-category.window="loadCategory($event.detail.slug)">
    {{-- Дерево категорий слева --}}
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
                    class="block px-3 py-2 rounded-lg text-sm transition-colors {{ !$selectedCategoryId ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400 font-medium' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                    Все категории
                </a>
                @foreach($categoryTree as $root)
                    @include('manufacturer.catalog._tree_node', ['node' => $root, 'level' => 0, 'selectedId' => $selectedCategoryId, 'selectedSlug' => $selectedCategory?->slug])
                @endforeach
            </nav>
        </div>
    </aside>

    {{-- Товары справа (обновляются без перезагрузки при выборе категории) --}}
    <main class="flex-1 min-w-0">
        <div id="catalog-products-container">
            @include('manufacturer.catalog._products', [
                'products' => $products,
                'selectedCategory' => $selectedCategory ?? null,
                'selectedCategoryId' => $selectedCategoryId,
                'filterableAttributes' => $filterableAttributes ?? collect(),
                'appliedFilters' => $appliedFilters ?? [],
            ])
        </div>
    </main>
</div>

@push('scripts')
<script>
function catalogApp(initialCategoryId, initialCategorySlug) {
    return {
        selectedCategoryId: initialCategoryId,
        selectedCategorySlug: initialCategorySlug || null,
        loading: false,
        loadCategory(slug) {
            if (this.loading) return;
            this.loading = true;
            const params = new URLSearchParams();
            if (slug != null && slug !== '') params.set('category', slug);
            const url = '{{ route("manufacturer.catalog.products") }}' + (params.toString() ? '?' + params : '');
            const baseCatalogUrl = '{{ url("/manufacturer/catalog") }}';
            const catalogUrl = (slug != null && slug !== '') ? (baseCatalogUrl + '/' + encodeURIComponent(slug)) : baseCatalogUrl;
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } })
                .then(r => r.text())
                .then(html => {
                    document.getElementById('catalog-products-container').innerHTML = html;
                    history.replaceState({ category: slug }, '', catalogUrl);
                    this.selectedCategorySlug = slug || null;
                    this.selectedCategoryId = null; // id не храним в state, только slug
                })
                .finally(() => { this.loading = false; });
        },
        collapseAll() {
            document.querySelectorAll('[x-data]').forEach(el => {
                if (el.__x && el.__x.$data && typeof el.__x.$data.open === 'boolean') {
                    el.__x.$data.open = false;
                }
            });
        }
    };
}
</script>
@endpush
@endsection
