@php
    $products = $products ?? collect();
    $selectedCategory = $selectedCategory ?? null;
    $selectedCategoryId = $selectedCategoryId ?? null;
    $filterableAttributes = $filterableAttributes ?? collect();
    $appliedFilters = $appliedFilters ?? [];
    $listingParams = $listingParams ?? null;
    $visibleStructuralFilters = $visibleStructuralFilters ?? [];
    $filterDistributors = $filterDistributors ?? collect();
    $filterManufacturers = $filterManufacturers ?? collect();
    $manufacturerProfileId = $manufacturerProfileId ?? null;
    $companyRegionName = $companyRegionName ?? null;
    $catalogIndexRoute = $catalogIndexRoute ?? 'manufacturer.catalog.index';
    $catalogShowRoute = $catalogShowRoute ?? 'manufacturer.catalog.show';
    $searchQuery = $searchQuery ?? '';
    $searchTerms = $searchTerms ?? [];
    $searchHighlighter = $searchTerms !== [] ? app(\App\Services\Catalog\CatalogSearchHighlightService::class) : null;
    $showNomenclatureLink = $showNomenclatureLink ?? false;
    $hasActiveFilters = ! empty($appliedFilters)
        || $searchQuery !== ''
        || ($listingParams?->hasStructuralFilters() ?? false);
    $hasDeferredAttributeFilters = $filterableAttributes->contains(function ($attr) use ($selectedCategory, $manufacturerProfileId) {
        $display = $selectedCategory
            ? $attr->resolvedFilterDisplayType($selectedCategory, $manufacturerProfileId)
            : ($attr->filter_display_type ?: \App\Models\ProductAttribute::FILTER_DISPLAY_TEXT);

        if ($attr->type === 'boolean') {
            return false;
        }
        if (in_array($display, [
            \App\Models\ProductAttribute::FILTER_DISPLAY_CHECKBOXES,
            \App\Models\ProductAttribute::FILTER_DISPLAY_SELECT,
        ], true)) {
            return false;
        }

        return true;
    });
    $activeFilterCount = count($appliedFilters)
        + ($searchQuery !== '' ? 1 : 0)
        + ($listingParams?->hasStructuralFilters() ? 1 : 0);
@endphp
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
        <h3 class="font-medium text-gray-900 dark:text-white">
            @if($selectedCategoryId)
                Товары в выбранной категории
            @else
                Все товары каталога
            @endif
        </h3>
        <p class="text-sm text-gray-500 mt-1">Товары с привязкой к категории, опубликованные в каталоге</p>
        @if($companyRegionName)
            <p class="text-xs text-gray-500 mt-1">Регион: {{ $companyRegionName }}</p>
        @endif
    </div>

    @if($visibleStructuralFilters !== [] || $filterableAttributes->isNotEmpty())
        <div class="lg:hidden px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30 flex items-center justify-between gap-3">
            <button type="button" @click="catalogFiltersOpen = true"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-800 dark:text-gray-200 shadow-sm hover:border-[#c3242a]">
                <span>Фильтры</span>
                @if($hasActiveFilters)
                    <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 rounded-full bg-[#c3242a] text-white text-[11px] font-semibold">{{ $activeFilterCount }}</span>
                @endif
            </button>
            @if($hasActiveFilters)
                <button type="button" @click="$dispatch('catalog-reset-filters')"
                    class="text-sm text-gray-500 hover:text-[#c3242a] shrink-0">
                    Сбросить
                </button>
            @endif
        </div>

        <div x-show="catalogFiltersOpen" x-transition.opacity
            class="lg:hidden fixed inset-0 z-40 bg-black/40"
            @click="catalogFiltersOpen = false" x-cloak></div>

        <div class="catalog-filters-panel border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30
            hidden lg:block
            lg:static lg:inset-auto lg:z-auto lg:w-auto lg:max-w-none lg:shadow-none lg:overflow-visible
            fixed inset-y-0 right-0 z-50 w-full max-w-sm shadow-2xl overflow-y-auto"
            :class="{ '!block': catalogFiltersOpen }">
            <div class="lg:hidden sticky top-0 z-10 flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                <h4 class="font-medium text-gray-900 dark:text-white">Фильтры</h4>
                <button type="button" @click="catalogFiltersOpen = false"
                    class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">Закрыть</button>
            </div>
            <div class="p-4">
            <form id="catalog-filters-form" method="get"
                action="{{ $selectedCategory ? route($catalogIndexRoute, ['category' => $selectedCategory->slug]) : route($catalogIndexRoute) }}"
                @submit.prevent="$dispatch('catalog-apply-filters')"
                class="flex flex-col gap-6">
                @include('catalog._structural_filters', [
                    'listingParams' => $listingParams,
                    'visibleStructuralFilters' => $visibleStructuralFilters,
                    'filterDistributors' => $filterDistributors,
                    'filterManufacturers' => $filterManufacturers,
                    'priceBounds' => $priceBounds ?? null,
                ])
                @if($filterableAttributes->isNotEmpty())
                <div class="flex flex-wrap items-end gap-6">
                @foreach($filterableAttributes as $attr)
                @php
                    $display = $selectedCategory
                        ? $attr->resolvedFilterDisplayType($selectedCategory, $manufacturerProfileId)
                        : ($attr->filter_display_type ?: \App\Models\ProductAttribute::FILTER_DISPLAY_TEXT);
                    $options = $selectedCategory
                        ? $attr->effectiveFilterOptions($selectedCategory, $manufacturerProfileId)
                        : ($attr->options ?? []);
                @endphp
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $attr->name }}</label>
                    @if($display === \App\Models\ProductAttribute::FILTER_DISPLAY_RANGE)
                        @php
                            $r = $appliedFilters[$attr->id] ?? null;
                            $rmin = is_array($r) ? ($r['min'] ?? '') : '';
                            $rmax = is_array($r) ? ($r['max'] ?? '') : '';
                        @endphp
                        <div class="flex flex-wrap items-center gap-2">
                            <input type="number" name="attr[{{ $attr->id }}][min]" value="{{ $rmin }}" step="any" placeholder="От"
                                @input.debounce.500ms="$dispatch('catalog-apply-filters-debounced')"
                                class="w-28 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm shadow-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
                            <span class="text-gray-400">—</span>
                            <input type="number" name="attr[{{ $attr->id }}][max]" value="{{ $rmax }}" step="any" placeholder="До"
                                @input.debounce.500ms="$dispatch('catalog-apply-filters-debounced')"
                                class="w-28 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm shadow-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
                        </div>
                    @elseif($attr->type === 'boolean')
                        @php $v = $appliedFilters[$attr->id] ?? ''; @endphp
                        <div class="flex flex-wrap gap-2">
                            <label class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200 cursor-pointer transition-colors hover:border-[#c3242a] has-[:checked]:border-[#c3242a] has-[:checked]:bg-red-50 dark:has-[:checked]:bg-red-900/20">
                                <input type="radio" name="attr[{{ $attr->id }}]" value="1" {{ $v === '1' ? 'checked' : '' }}
                                    @change="$dispatch('catalog-apply-filters')"
                                    class="h-4 w-4 border-gray-300 focus:ring-[#c3242a] focus:ring-offset-0"
                                    style="accent-color: #c3242a;" />
                                <span>Да</span>
                            </label>
                            <label class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200 cursor-pointer transition-colors hover:border-[#c3242a] has-[:checked]:border-[#c3242a] has-[:checked]:bg-red-50 dark:has-[:checked]:bg-red-900/20">
                                <input type="radio" name="attr[{{ $attr->id }}]" value="0" {{ $v === '0' ? 'checked' : '' }}
                                    @change="$dispatch('catalog-apply-filters')"
                                    class="h-4 w-4 border-gray-300 focus:ring-[#c3242a] focus:ring-offset-0"
                                    style="accent-color: #c3242a;" />
                                <span>Нет</span>
                            </label>
                            <label class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200 cursor-pointer transition-colors hover:border-[#c3242a] has-[:checked]:border-[#c3242a] has-[:checked]:bg-red-50 dark:has-[:checked]:bg-red-900/20">
                                <input type="radio" name="attr[{{ $attr->id }}]" value="" {{ $v === '' ? 'checked' : '' }}
                                    @change="$dispatch('catalog-apply-filters')"
                                    class="h-4 w-4 border-gray-300 focus:ring-[#c3242a] focus:ring-offset-0"
                                    style="accent-color: #c3242a;" />
                                <span>Любой</span>
                            </label>
                        </div>
                    @elseif($display === \App\Models\ProductAttribute::FILTER_DISPLAY_CHECKBOXES && !empty($options))
                        @php $vals = (array) ($appliedFilters[$attr->id] ?? []); @endphp
                        <div class="relative w-full min-w-[180px] max-w-[260px]" x-data="{
                            open: false,
                            selected: {{ json_encode($vals) }},
                            toggleOption(val) {
                                const i = this.selected.indexOf(val);
                                if (i >= 0) this.selected = this.selected.filter((_, idx) => idx !== i);
                                else this.selected = [...this.selected, val];
                            }
                        }" @click.away="open = false">
                            <button type="button" @click="open = !open"
                                class="w-full px-3 py-2 pr-9 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-left text-sm shadow-sm hover:border-[#c3242a] focus:ring-2 focus:ring-[#c3242a] focus:border-transparent transition-colors">
                                <span class="truncate" x-text="selected.length === 0 ? 'Любой' : (selected.length === 1 ? selected[0] : 'Выбрано: ' + selected.length)">Любой</span>
                            </button>
                            @include('catalog._filter_chevron')
                            <div x-show="open" x-transition class="absolute z-20 mt-1 left-0 right-0 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 shadow-lg max-h-56 overflow-y-auto py-1" x-cloak>
                                @foreach($options as $opt)
                                @php $checked = is_array($vals) && in_array($opt, $vals, true); @endphp
                                <label class="flex items-center gap-2 px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 cursor-pointer text-sm">
                                    <input type="checkbox" name="attr[{{ $attr->id }}][]" value="{{ $opt }}" {{ $checked ? 'checked' : '' }}
                                        @change="toggleOption({{ json_encode($opt) }}); $dispatch('catalog-apply-filters')"
                                        class="h-4 w-4 rounded border-gray-300 focus:ring-[#c3242a] focus:ring-offset-0"
                                        style="accent-color: #c3242a;" />
                                    <span>{{ $opt }}</span>
                                </label>
                                @endforeach
                            </div>
                        </div>
                    @elseif($display === \App\Models\ProductAttribute::FILTER_DISPLAY_SELECT && !empty($options))
                        @php $sv = $appliedFilters[$attr->id] ?? ''; $sv = is_array($sv) ? ($sv[0] ?? '') : (string) $sv; @endphp
                        @include('catalog._filter_select', [
                            'name' => 'attr['.$attr->id.']',
                            'value' => $sv,
                            'options' => $options,
                            'autoApply' => true,
                        ])
                    @else
                        <input type="text" name="attr[{{ $attr->id }}]"
                            value="{{ is_array($appliedFilters[$attr->id] ?? '') ? '' : ($appliedFilters[$attr->id] ?? '') }}"
                            placeholder="Любое"
                            class="w-40 min-w-[140px] px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm shadow-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
                    @endif
                </div>
                @endforeach
                </div>
                @endif
                <div class="flex flex-wrap items-center gap-4">
                @if($hasDeferredAttributeFilters)
                <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg bg-[#c3242a] text-white text-sm font-medium hover:bg-[#a01e24]">
                    Применить
                </button>
                @endif
                @if($hasActiveFilters)
                <button type="button"
                    @click="$dispatch('catalog-reset-filters')"
                    class="text-sm text-gray-500 hover:text-[#c3242a] hidden lg:inline">
                    Сбросить
                </button>
                @endif
                </div>
            </form>
            <div class="lg:hidden sticky bottom-0 p-4 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                <button type="button" @click="catalogFiltersOpen = false"
                    class="w-full inline-flex items-center justify-center px-4 py-2.5 rounded-lg bg-[#c3242a] text-white text-sm font-medium hover:bg-[#a01e24]">
                    Показать результаты
                </button>
            </div>
            </div>
        </div>
    @endif

    <div class="p-4">
        @if($products->isEmpty())
            @include('catalog._empty_state', ['emptyState' => $emptyState ?? null])
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach($products as $product)
                    @php $isUnavailable = ($catalogShowRoute === 'buyer.catalog.show') && ($product->unavailable_in_region ?? false); @endphp
                    <a href="{{ route($catalogShowRoute, $product) }}" class="block rounded-lg border overflow-hidden transition-colors {{ $isUnavailable ? 'border-gray-300 opacity-80' : 'border-gray-200 dark:border-gray-600 hover:border-[#c3242a]' }}">
                        <div class="aspect-square bg-gray-100 dark:bg-gray-700 flex items-center justify-center relative">
                            @if($product->primaryImage())
                                <img src="{{ $product->primaryImage()->url }}" alt="" class="w-full h-full object-cover" />
                            @else
                                <span class="text-gray-400 text-sm">Без фото</span>
                            @endif
                        </div>
                        <div class="p-3">
                            <p class="text-sm font-medium text-gray-900 dark:text-white line-clamp-2">{!! $searchHighlighter ? $searchHighlighter->highlight($product->name, $searchTerms) : e($product->name) !!}</p>
                            @php
                                $listingSku = $product->sku ?: $product->manufacturer_sku;
                            @endphp
                            @if($listingSku && $searchHighlighter)
                                <p class="text-xs text-gray-500 mt-0.5">{!! $searchHighlighter->highlight($listingSku, $searchTerms) !!}</p>
                            @endif
                            @if($product->catalogMarks() !== [])
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @foreach($product->catalogMarks() as $mark)
                                        <span class="text-[10px] font-medium px-1.5 py-0.5 rounded {{ $mark['class'] }}">{{ $mark['label'] }}</span>
                                    @endforeach
                                </div>
                            @endif
                            @if($isUnavailable)
                                <p class="mt-1 text-[11px] text-gray-600 bg-gray-100 dark:bg-gray-700 dark:text-gray-300 rounded px-2 py-0.5 inline-flex">
                                    Недоступно в вашем регионе
                                </p>
                            @endif
                            @if($product->has_visible_analogs ?? false)
                                <p class="mt-1 text-[11px] text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/30 rounded px-2 py-0.5 inline-flex gap-1">
                                    <span>↔</span> Есть аналоги
                                </p>
                            @endif
                            <p class="text-xs text-gray-500 mt-1">{{ $product->category?->name }}</p>
                            @if($product->manufacturerProfile && $catalogShowRoute === 'buyer.catalog.show')
                                <p class="text-xs text-gray-400">{{ $product->manufacturerProfile->short_name ?: $product->manufacturerProfile->full_name }}</p>
                            @endif
                            @php
                                $listingPrice = null;
                                if ($catalogShowRoute === 'buyer.catalog.show' && ! $isUnavailable && $product->distributor_display_price !== null) {
                                    $listingPrice = (float) $product->distributor_display_price;
                                } elseif ($catalogShowRoute !== 'buyer.catalog.show' && $product->base_price) {
                                    $listingPrice = (float) $product->base_price;
                                }
                            @endphp
                            @if(! $isUnavailable)
                                <p class="text-sm font-semibold text-[#c3242a] mt-1">{{ $listingPrice !== null ? number_format($listingPrice, 0, ',', ' ') . ' ₽' : '—' }}</p>
                                @if($catalogShowRoute === 'buyer.catalog.show' && $listingPrice !== null)
                                    <p class="text-[10px] text-gray-400 mt-0.5">цена дистрибьютора</p>
                                @endif
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
            @if($products->hasPages())
                <div class="mt-6 catalog-pagination">{{ $products->links() }}</div>
            @endif
        @endif
    </div>
</div>
