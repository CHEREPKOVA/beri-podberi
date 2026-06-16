@php
    $searchQuery = $searchQuery ?? '';
    $selectedCategory = $selectedCategory ?? null;
    $searchScope = $listingParams?->searchScope ?? \App\Services\Catalog\CatalogListingParams::SEARCH_SCOPE_CATEGORY;
@endphp
<div class="relative z-40 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-4 overflow-visible"
    @click.away="closeSuggestions()">
    <div class="flex flex-col gap-3">
        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
            <label for="catalog-search-input" class="text-sm font-medium text-gray-700 dark:text-gray-300 shrink-0">
                Поиск в каталоге
            </label>
            <div class="flex flex-1 items-center gap-2 min-w-0">
                <div class="relative flex-1 min-w-0">
                    <input type="text" id="catalog-search-input" name="catalog_q" value="{{ $searchQuery }}"
                        placeholder="Название, артикул, бренд, аналог…"
                        autocomplete="off"
                        spellcheck="false"
                        autocorrect="off"
                        autocapitalize="off"
                        inputmode="search"
                        aria-autocomplete="list"
                        aria-controls="catalog-search-suggestions"
                        role="combobox"
                        class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100 shadow-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent"
                        @input.debounce.300ms="onSearchInput()"
                        @focus="onSearchFocus()"
                        @keydown.enter.prevent="submitSearch()"
                        @keydown.escape="closeSuggestions()" />

                    <div id="catalog-search-suggestions"
                        x-show="suggestOpen"
                        x-transition
                        class="absolute left-0 right-0 top-full z-50 mt-1 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-lg max-h-80 overflow-y-auto text-left"
                        x-cloak>
                        <p x-show="suggestLoading" class="px-3 py-3 text-sm text-gray-500">Поиск…</p>
                        <p x-show="!suggestLoading && !showPopularSearches() && !hasSuggestions()" class="px-3 py-3 text-sm text-gray-500">Ничего не найдено</p>
                        <div x-show="!suggestLoading && showPopularSearches()" class="py-1">
                            <p class="px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-gray-400">Популярные запросы</p>
                            <template x-for="item in suggestions.popular" :key="'pop-' + item.query">
                                <button type="button" @click="pickPopularSearch(item.query)"
                                    class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <span x-text="item.query"></span>
                                    <span class="text-gray-400 ml-1" x-text="'(' + item.count + ')'"></span>
                                </button>
                            </template>
                        </div>
                        <div x-show="!suggestLoading && hasSuggestions()">
                        <div x-show="suggestions.articles.length" class="py-1 border-b border-gray-100 dark:border-gray-700">
                            <p class="px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-gray-400">Артикулы</p>
                            <template x-for="item in suggestions.articles" :key="'a-' + item.product_id">
                                <a :href="item.url" class="block px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <span class="font-medium text-[#c3242a]" x-html="item.sku"></span>
                                    <span class="text-gray-500 ml-2" x-html="item.name"></span>
                                </a>
                            </template>
                        </div>
                        <div x-show="suggestions.products.length" class="py-1 border-b border-gray-100 dark:border-gray-700">
                            <p class="px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-gray-400">Товары</p>
                            <template x-for="item in suggestions.products" :key="'p-' + item.id">
                                <a :href="item.url" class="flex items-center gap-3 px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <span class="w-8 h-8 shrink-0 rounded bg-gray-100 dark:bg-gray-700 overflow-hidden flex items-center justify-center">
                                        <img x-show="item.image" :src="item.image" alt="" class="w-full h-full object-cover" />
                                        <span x-show="!item.image" class="text-[10px] text-gray-400">—</span>
                                    </span>
                                    <span>
                                        <span class="block font-medium text-gray-900 dark:text-gray-100" x-html="item.name"></span>
                                        <span class="block text-xs text-gray-400" x-text="item.sku || ''"></span>
                                    </span>
                                </a>
                            </template>
                        </div>
                        <div x-show="suggestions.categories.length" class="py-1 border-b border-gray-100 dark:border-gray-700">
                            <p class="px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-gray-400">Категории</p>
                            <template x-for="item in suggestions.categories" :key="'c-' + item.id">
                                <button type="button" @click="pickSuggestCategory(item.slug)"
                                    class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <span x-text="item.name"></span>
                                    <span class="text-gray-400 ml-1" x-text="'(' + item.count + ')'"></span>
                                </button>
                            </template>
                        </div>
                        <div x-show="suggestions.manufacturers.length" class="py-1">
                            <p class="px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-gray-400">Производители</p>
                            <template x-for="item in suggestions.manufacturers" :key="'m-' + item.id">
                                <button type="button" @click="pickSuggestManufacturer(item.id)"
                                    class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <span x-text="item.name"></span>
                                    <span class="text-gray-400 ml-1" x-text="'(' + item.count + ')'"></span>
                                </button>
                            </template>
                        </div>
                        </div>
                    </div>
                </div>
                <button type="button" @click="submitSearch()"
                    class="shrink-0 inline-flex items-center justify-center px-5 py-2.5 rounded-lg bg-[#c3242a] text-white text-sm font-medium shadow-sm hover:bg-[#a01e24] focus:outline-none focus:ring-2 focus:ring-[#c3242a] focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                    Найти
                </button>
            </div>
        </div>
        @if($selectedCategory)
            <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300 cursor-pointer w-fit">
                <input type="checkbox" id="catalog-search-scope-global" name="search_scope" value="global"
                    {{ $searchScope === \App\Services\Catalog\CatalogListingParams::SEARCH_SCOPE_GLOBAL ? 'checked' : '' }}
                    class="h-4 w-4 rounded border-gray-300 focus:ring-[#c3242a] focus:ring-offset-0"
                    style="accent-color: #c3242a;" />
                <span>Искать во всём каталоге</span>
            </label>
        @else
            <input type="hidden" id="catalog-search-scope-global" name="search_scope" value="global" />
        @endif
    </div>
</div>
