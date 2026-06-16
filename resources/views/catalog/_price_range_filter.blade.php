@php
    $priceMin = $priceMin ?? null;
    $priceMax = $priceMax ?? null;
    $priceBounds = $priceBounds ?? null;
    $boundsMin = isset($priceBounds['min']) ? (int) $priceBounds['min'] : null;
    $boundsMax = isset($priceBounds['max']) ? (int) $priceBounds['max'] : null;
    $hasSlider = $boundsMin !== null && $boundsMax !== null && $boundsMax > $boundsMin;
@endphp
<div class="flex flex-col gap-2 min-w-[220px]"
    @if($hasSlider)
    x-data="catalogPriceRangeFilter({
        boundsMin: {{ $boundsMin }},
        boundsMax: {{ $boundsMax }},
        initialMin: {{ $priceMin !== null ? (int) $priceMin : 'null' }},
        initialMax: {{ $priceMax !== null ? (int) $priceMax : 'null' }},
    })"
    @endif>
    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Цена, ₽</span>
    <div class="flex flex-wrap items-center gap-2">
        <input type="number" name="price_min" x-ref="priceMinInput"
            value="{{ $priceMin !== null ? (int) $priceMin : '' }}" step="1" min="0" placeholder="От"
            @if($hasSlider) @input="onMinInput($event)" @else @input.debounce.500ms="$dispatch('catalog-apply-filters-debounced')" @endif
            class="w-24 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm shadow-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
        <span class="text-gray-400">—</span>
        <input type="number" name="price_max" x-ref="priceMaxInput"
            value="{{ $priceMax !== null ? (int) $priceMax : '' }}" step="1" min="0" placeholder="До"
            @if($hasSlider) @input="onMaxInput($event)" @else @input.debounce.500ms="$dispatch('catalog-apply-filters-debounced')" @endif
            class="w-24 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm shadow-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
    </div>
    @if($hasSlider)
        <div class="pt-1 space-y-1">
            <div class="relative h-6 flex items-center">
                <input type="range" :min="boundsMin" :max="boundsMax" x-model.number="minValue"
                    @input="onMinSlider()"
                    class="catalog-price-range absolute w-full h-1 appearance-none bg-transparent pointer-events-none [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:h-4 [&::-webkit-slider-thumb]:w-4 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-[#c3242a] [&::-webkit-slider-thumb]:border-2 [&::-webkit-slider-thumb]:border-white [&::-webkit-slider-thumb]:shadow [&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:h-4 [&::-moz-range-thumb]:w-4 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:bg-[#c3242a] [&::-moz-range-thumb]:border-2 [&::-moz-range-thumb]:border-white" />
                <input type="range" :min="boundsMin" :max="boundsMax" x-model.number="maxValue"
                    @input="onMaxSlider()"
                    class="catalog-price-range absolute w-full h-1 appearance-none bg-transparent pointer-events-none [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:h-4 [&::-webkit-slider-thumb]:w-4 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-[#c3242a] [&::-webkit-slider-thumb]:border-2 [&::-webkit-slider-thumb]:border-white [&::-webkit-slider-thumb]:shadow [&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:h-4 [&::-moz-range-thumb]:w-4 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:bg-[#c3242a] [&::-moz-range-thumb]:border-2 [&::-moz-range-thumb]:border-white" />
                <div class="absolute left-0 right-0 h-1 rounded-full bg-gray-200 dark:bg-gray-600"></div>
                <div class="absolute h-1 rounded-full bg-[#c3242a]/70"
                    :style="'left:' + minPercent() + '%; right:' + (100 - maxPercent()) + '%'"></div>
            </div>
            <p class="text-[11px] text-gray-400">
                <span x-text="formatPrice(minValue)"></span> — <span x-text="formatPrice(maxValue)"></span>
            </p>
        </div>
    @endif
</div>
