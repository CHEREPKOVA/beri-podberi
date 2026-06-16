@php
    $priceMin = $priceMin ?? null;
    $priceMax = $priceMax ?? null;
@endphp
<div class="flex flex-col gap-2 min-w-[220px]">
    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Цена, ₽</span>
    <div class="flex flex-wrap items-center gap-2">
        <input type="number" name="price_min"
            value="{{ $priceMin !== null ? (int) $priceMin : '' }}" step="1" min="0" placeholder="От"
            @input.debounce.500ms="$dispatch('catalog-apply-filters-debounced')"
            class="w-24 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm shadow-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
        <span class="text-gray-400">—</span>
        <input type="number" name="price_max"
            value="{{ $priceMax !== null ? (int) $priceMax : '' }}" step="1" min="0" placeholder="До"
            @input.debounce.500ms="$dispatch('catalog-apply-filters-debounced')"
            class="w-24 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm shadow-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
    </div>
</div>
