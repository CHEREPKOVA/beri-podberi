@php
    $products = $products ?? collect();
    $selectedCategory = $selectedCategory ?? null;
    $selectedCategoryId = $selectedCategoryId ?? null;
    $filterableAttributes = $filterableAttributes ?? collect();
    $appliedFilters = $appliedFilters ?? [];
    $manufacturerProfileId = $manufacturerProfileId ?? null;
@endphp
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
    <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h3 class="font-medium text-gray-900 dark:text-white">
                @if($selectedCategoryId)
                    Товары в выбранной категории
                @else
                    Все товары каталога
                @endif
            </h3>
            <p class="text-sm text-gray-500 mt-1">Товары с привязкой к категории, опубликованные в каталоге</p>
        </div>
    </div>

    @if($filterableAttributes->isNotEmpty())
        <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30">
            <form id="catalog-filters-form" method="get" action="{{ $selectedCategory ? route('manufacturer.catalog.index', ['category' => $selectedCategory->slug]) : route('manufacturer.catalog.index') }}" class="flex flex-wrap items-end gap-6">
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
                                class="w-28 px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
                            <span class="text-gray-400">—</span>
                            <input type="number" name="attr[{{ $attr->id }}][max]" value="{{ $rmax }}" step="any" placeholder="До"
                                class="w-28 px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
                        </div>
                    @elseif($attr->type === 'boolean')
                        <div class="flex flex-wrap gap-2">
                            @php $v = $appliedFilters[$attr->id] ?? ''; @endphp
                            <label class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200 cursor-pointer transition-colors hover:border-[#c3242a] has-[:checked]:border-[#c3242a] has-[:checked]:bg-red-50 dark:has-[:checked]:bg-red-900/20">
                                <input type="radio" name="attr[{{ $attr->id }}]" value="1" {{ $v === '1' ? 'checked' : '' }}
                                    class="h-4 w-4 border-gray-300 text-[#c3242a] focus:ring-[#c3242a]" />
                                <span>Да</span>
                            </label>
                            <label class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200 cursor-pointer transition-colors hover:border-[#c3242a] has-[:checked]:border-[#c3242a] has-[:checked]:bg-red-50 dark:has-[:checked]:bg-red-900/20">
                                <input type="radio" name="attr[{{ $attr->id }}]" value="0" {{ $v === '0' ? 'checked' : '' }}
                                    class="h-4 w-4 border-gray-300 text-[#c3242a] focus:ring-[#c3242a]" />
                                <span>Нет</span>
                            </label>
                            <label class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200 cursor-pointer transition-colors hover:border-[#c3242a] has-[:checked]:border-[#c3242a] has-[:checked]:bg-red-50 dark:has-[:checked]:bg-red-900/20">
                                <input type="radio" name="attr[{{ $attr->id }}]" value="" {{ $v === '' ? 'checked' : '' }}
                                    class="h-4 w-4 border-gray-300 text-[#c3242a] focus:ring-[#c3242a]" />
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
                                class="w-full px-3 py-2 pr-9 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent flex items-center justify-between gap-2">
                                <span class="truncate" x-text="selected.length === 0 ? 'Любой' : (selected.length === 1 ? selected[0] : 'Выбрано: ' + selected.length)">Любой</span>
                                <svg class="w-4 h-4 shrink-0 text-gray-500 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="open" x-transition
                                class="absolute z-20 mt-1 left-0 right-0 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 shadow-lg max-h-56 overflow-y-auto py-1"
                                x-cloak>
                                @foreach($options as $opt)
                                @php $checked = is_array($vals) && in_array($opt, $vals, true); @endphp
                                <label class="flex items-center gap-2 px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 cursor-pointer text-sm text-gray-700 dark:text-gray-300">
                                    <input type="checkbox" name="attr[{{ $attr->id }}][]" value="{{ $opt }}"
                                        {{ $checked ? 'checked' : '' }}
                                        @change="toggleOption({{ json_encode($opt) }})"
                                        class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a] shrink-0" />
                                    <span>{{ $opt }}</span>
                                </label>
                                @endforeach
                            </div>
                        </div>
                    @elseif($display === \App\Models\ProductAttribute::FILTER_DISPLAY_SELECT && !empty($options))
                        @php $sv = $appliedFilters[$attr->id] ?? ''; $sv = is_array($sv) ? ($sv[0] ?? '') : (string)$sv; @endphp
                        <div class="relative w-full min-w-[180px] max-w-[240px]">
                            <select name="attr[{{ $attr->id }}]"
                                class="w-full appearance-none px-3 py-2 pr-9 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200 shadow-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent transition-colors hover:border-[#c3242a]">
                                <option value="">Любой</option>
                                @foreach($options as $opt)
                                    <option value="{{ $opt }}" {{ $sv === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                @endforeach
                            </select>
                            <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    @else
                        <input type="text"
                            name="attr[{{ $attr->id }}]"
                            value="{{ is_array($appliedFilters[$attr->id] ?? '') ? '' : ($appliedFilters[$attr->id] ?? '') }}"
                            placeholder="Любое"
                            class="w-40 px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
                    @endif
                </div>
                @endforeach
                <button type="submit" class="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-[#c3242a] text-white text-sm font-medium shadow-sm transition-colors hover:bg-[#a01e24] focus:outline-none focus:ring-2 focus:ring-[#c3242a] focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                    Применить
                </button>
                @if(!empty($appliedFilters))
                <a href="{{ $selectedCategory ? route('manufacturer.catalog.index', ['category' => $selectedCategory->slug]) : route('manufacturer.catalog.index') }}"
                    class="text-sm text-gray-500 hover:text-[#c3242a]">
                    Сбросить фильтры
                </a>
                @endif
            </form>
        </div>
    @endif

    <div class="p-4">
        @if($products->isEmpty())
            <div class="py-12 text-center">
                <svg class="mx-auto w-12 h-12 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                </svg>
                <p class="mt-4 text-gray-500 dark:text-gray-400">В этой категории пока нет товаров</p>
                <a href="{{ route('manufacturer.products.index') }}" class="inline-flex items-center gap-2 mt-4 px-4 py-2 bg-[#c3242a] text-white rounded-lg hover:bg-[#a01e24] text-sm font-medium">
                    Перейти в номенклатуру
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach($products as $product)
                    <a href="{{ route('manufacturer.catalog.show', $product) }}" class="block rounded-lg border border-gray-200 dark:border-gray-600 hover:border-[#c3242a] dark:hover:border-red-500 overflow-hidden transition-colors">
                        <div class="aspect-square bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                            @if($product->primaryImage())
                                <img src="{{ $product->primaryImage()->url }}" alt="" class="w-full h-full object-cover" />
                            @else
                                <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            @endif
                        </div>
                        <div class="p-3">
                            <p class="text-sm font-medium text-gray-900 dark:text-white line-clamp-2">{{ $product->name }}</p>
                            @if((int) (($product->analogs_count ?? 0) + ($product->analog_of_count ?? 0)) > 0)
                                <p class="mt-1 inline-flex items-center gap-1 text-[11px] text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/30 rounded px-2 py-0.5">
                                    <span>↔</span>
                                    Есть аналоги
                                </p>
                            @endif
                            <p class="text-xs text-gray-500 mt-1">{{ $product->category?->name }}</p>
                            <p class="text-sm font-semibold text-[#c3242a] mt-1">{{ $product->base_price ? number_format($product->base_price, 0, ',', ' ') . ' ₽' : '—' }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
            @if($products->hasPages())
                <div class="mt-6">
                    {{ $products->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
