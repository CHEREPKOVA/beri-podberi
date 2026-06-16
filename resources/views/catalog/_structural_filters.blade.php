@php
    use App\Services\Catalog\CatalogListingParams;

    $listingParams = $listingParams ?? new CatalogListingParams();
    $visibleStructuralFilters = $visibleStructuralFilters ?? [];
    $filterDistributors = $filterDistributors ?? collect();
    $filterManufacturers = $filterManufacturers ?? collect();
    $priceBounds = $priceBounds ?? null;
    $selectedDistributorIds = $listingParams->distributorIds;
    $selectedManufacturerIds = $listingParams->manufacturerIds;
    $selectedStock = $listingParams->stock;
    $priceMin = $listingParams->priceMin;
    $priceMax = $listingParams->priceMax;
@endphp
@if($visibleStructuralFilters !== [])
<div class="flex flex-wrap items-end gap-6">
    @if(in_array('distributor', $visibleStructuralFilters, true) && $filterDistributors->isNotEmpty())
        @php $distOpen = count($selectedDistributorIds) > 0; @endphp
        <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Поставщик</label>
            <div class="relative w-full min-w-[180px] max-w-[260px]" x-data="{
                open: false,
                selected: {{ json_encode(array_map('strval', $selectedDistributorIds)) }},
                toggleOption(val) {
                    const i = this.selected.indexOf(val);
                    if (i >= 0) this.selected = this.selected.filter((_, idx) => idx !== i);
                    else this.selected = [...this.selected, val];
                }
            }" @click.away="open = false">
                <button type="button" @click="open = !open"
                    class="w-full px-3 py-2 pr-9 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-left text-sm shadow-sm hover:border-[#c3242a] focus:ring-2 focus:ring-[#c3242a] focus:border-transparent transition-colors">
                    <span class="truncate" x-text="selected.length === 0 ? 'Любой' : 'Выбрано: ' + selected.length">Любой</span>
                </button>
                @include('catalog._filter_chevron')
                <div x-show="open" x-transition class="absolute z-20 mt-1 left-0 right-0 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 shadow-lg max-h-56 overflow-y-auto py-1" x-cloak>
                    @foreach($filterDistributors as $distributor)
                        @php
                            $distId = (string) $distributor->id;
                            $checked = in_array($distributor->id, $selectedDistributorIds, true);
                            $facetCount = $distributor->facet_count ?? null;
                            $isDisabled = $facetCount !== null && $facetCount === 0 && ! $checked;
                        @endphp
                        <label class="flex items-center gap-2 px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 cursor-pointer text-sm {{ $isDisabled ? 'opacity-40 cursor-not-allowed' : '' }}">
                            <input type="checkbox" name="distributor_ids[]" value="{{ $distributor->id }}" {{ $checked ? 'checked' : '' }} {{ $isDisabled ? 'disabled' : '' }}
                                @change="toggleOption({{ json_encode($distId) }}); $dispatch('catalog-apply-filters')"
                                class="h-4 w-4 rounded border-gray-300 focus:ring-[#c3242a] focus:ring-offset-0"
                                style="accent-color: #c3242a;" />
                            <span>{{ $distributor->displayName() }}@if($facetCount !== null) <span class="text-gray-400">({{ $facetCount }})</span>@endif</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if(in_array('manufacturer', $visibleStructuralFilters, true) && $filterManufacturers->isNotEmpty())
        <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Производитель</label>
            <div class="relative w-full min-w-[180px] max-w-[260px]" x-data="{
                open: false,
                selected: {{ json_encode(array_map('strval', $selectedManufacturerIds)) }},
                toggleOption(val) {
                    const i = this.selected.indexOf(val);
                    if (i >= 0) this.selected = this.selected.filter((_, idx) => idx !== i);
                    else this.selected = [...this.selected, val];
                }
            }" @click.away="open = false">
                <button type="button" @click="open = !open"
                    class="w-full px-3 py-2 pr-9 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-left text-sm shadow-sm hover:border-[#c3242a] focus:ring-2 focus:ring-[#c3242a] focus:border-transparent transition-colors">
                    <span class="truncate" x-text="selected.length === 0 ? 'Любой' : 'Выбрано: ' + selected.length">Любой</span>
                </button>
                @include('catalog._filter_chevron')
                <div x-show="open" x-transition class="absolute z-20 mt-1 left-0 right-0 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 shadow-lg max-h-56 overflow-y-auto py-1" x-cloak>
                    @foreach($filterManufacturers as $manufacturer)
                        @php
                            $mfrId = (string) $manufacturer->id;
                            $checked = in_array($manufacturer->id, $selectedManufacturerIds, true);
                            $facetCount = $manufacturer->facet_count ?? null;
                            $isDisabled = $facetCount !== null && $facetCount === 0 && ! $checked;
                            $mfrName = $manufacturer->short_name ?: $manufacturer->full_name;
                        @endphp
                        <label class="flex items-center gap-2 px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 cursor-pointer text-sm {{ $isDisabled ? 'opacity-40 cursor-not-allowed' : '' }}">
                            <input type="checkbox" name="manufacturer_ids[]" value="{{ $manufacturer->id }}" {{ $checked ? 'checked' : '' }} {{ $isDisabled ? 'disabled' : '' }}
                                @change="toggleOption({{ json_encode($mfrId) }}); $dispatch('catalog-apply-filters')"
                                class="h-4 w-4 rounded border-gray-300 focus:ring-[#c3242a] focus:ring-offset-0"
                                style="accent-color: #c3242a;" />
                            <span>{{ $mfrName }}@if($facetCount !== null) <span class="text-gray-400">({{ $facetCount }})</span>@endif</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if(in_array('stock', $visibleStructuralFilters, true))
        <div class="flex flex-col gap-1">
            <label for="catalog-stock-filter" class="text-sm font-medium text-gray-700 dark:text-gray-300">Наличие</label>
            @include('catalog._filter_select', [
                'name' => 'stock',
                'value' => $selectedStock ?? '',
                'options' => collect(CatalogListingParams::stockOptions())->mapWithKeys(
                    fn (string $key): array => [$key => CatalogListingParams::stockLabel($key)]
                )->all(),
                'placeholder' => 'Любое',
                'autoApply' => true,
            ])
        </div>
    @endif

    @if(in_array('price', $visibleStructuralFilters, true))
        @include('catalog._price_range_filter', [
            'priceMin' => $priceMin,
            'priceMax' => $priceMax,
            'priceBounds' => $priceBounds ?? null,
        ])
    @endif
</div>
@endif
