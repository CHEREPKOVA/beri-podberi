@php
    $searchQuery = $searchQuery ?? '';
@endphp
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-4">
    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
        <label for="catalog-search-input" class="text-sm font-medium text-gray-700 dark:text-gray-300 shrink-0">
            Поиск в каталоге
        </label>
        <div class="flex flex-1 items-center gap-2 min-w-0">
            <input type="search" id="catalog-search-input" name="search" value="{{ $searchQuery }}"
                placeholder="Название, артикул, характеристики…"
                class="flex-1 min-w-0 px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100 shadow-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent"
                @keydown.enter.prevent="$dispatch('catalog-apply-filters')" />
            <button type="button" @click="$dispatch('catalog-apply-filters')"
                class="shrink-0 inline-flex items-center justify-center px-5 py-2.5 rounded-lg bg-[#c3242a] text-white text-sm font-medium shadow-sm hover:bg-[#a01e24] focus:outline-none focus:ring-2 focus:ring-[#c3242a] focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                Найти
            </button>
        </div>
    </div>
</div>
