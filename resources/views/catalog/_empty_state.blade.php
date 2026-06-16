@php
    $emptyState = $emptyState ?? null;
@endphp
@if($emptyState)
<div class="py-12 px-4 text-center max-w-lg mx-auto">
    <p class="text-gray-600 dark:text-gray-300 font-medium">Товары не найдены</p>
    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Попробуйте изменить запрос или сбросить фильтры.</p>

    @if($emptyState['show_reset'] ?? false)
        <button type="button"
            @click="$dispatch('catalog-reset-filters')"
            class="mt-4 inline-flex items-center px-4 py-2 rounded-lg border border-[#c3242a] text-[#c3242a] text-sm font-medium hover:bg-red-50 dark:hover:bg-red-900/20">
            Сбросить фильтры
        </button>
    @endif

    @if(!empty($emptyState['similar_query']) && ($emptyState['similar_query_count'] ?? 0) > 0)
        <div class="mt-6 text-left">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Возможно, вы имели в виду</p>
            <button type="button"
                @click="document.getElementById('catalog-search-input').value = @js($emptyState['similar_query']); $dispatch('catalog-apply-filters')"
                class="text-sm text-[#c3242a] hover:underline">
                {{ $emptyState['similar_query'] }}
                <span class="text-gray-400">({{ $emptyState['similar_query_count'] }})</span>
            </button>
        </div>
    @endif

    @if(!empty($emptyState['suggested_analogs']))
        <div class="mt-6 text-left">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Возможно, подойдут аналоги</p>
            <ul class="space-y-2">
                @foreach($emptyState['suggested_analogs'] as $analog)
                <li>
                    <a href="{{ $analog['url'] }}"
                        class="block text-sm px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-600 hover:border-[#c3242a] hover:bg-red-50/50 dark:hover:bg-red-900/10 transition-colors">
                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ $analog['name'] }}</span>
                        @if(!empty($analog['sku']))
                            <span class="text-gray-400 ml-1">{{ $analog['sku'] }}</span>
                        @endif
                        @if($analog['unavailable'] ?? false)
                            <span class="block text-[11px] text-gray-500 mt-0.5">Недоступно в вашем регионе</span>
                        @endif
                    </a>
                </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(!empty($emptyState['suggested_categories']))
        <div class="mt-6 text-left">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Категории с совпадениями</p>
            <ul class="space-y-2">
                @foreach($emptyState['suggested_categories'] as $cat)
                <li>
                    <button type="button"
                        @click="$dispatch('load-category', { slug: @js($cat['slug']) })"
                        class="w-full text-left text-sm px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-600 hover:border-[#c3242a] hover:bg-red-50/50 dark:hover:bg-red-900/10 transition-colors">
                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ $cat['name'] }}</span>
                        <span class="text-gray-400 ml-1">({{ $cat['count'] }})</span>
                    </button>
                </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@else
<div class="py-12 text-center text-gray-500 dark:text-gray-400">Товары не найдены</div>
@endif
