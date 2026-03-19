<div x-data="{
    selectedRegions: @js($profile->regions->pluck('id')->toArray()),
    primaryRegion: {{ $profile->regions->where('pivot.is_primary', true)->first()?->id ?? 'null' }},
    search: '',
    selectedDistrict: ''
}">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Регионы присутствия</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Выберите регионы, в которых работает ваша компания</p>
        </div>
    </div>

    <form method="POST" action="{{ route('manufacturer.profile.regions.update') }}">
        @csrf
        @method('PUT')

        {{-- Фильтры --}}
        <div class="flex flex-col sm:flex-row gap-4 mb-6">
            <div class="flex-1">
                <input
                    type="text"
                    x-model="search"
                    placeholder="Поиск региона..."
                    class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                >
            </div>
            <div class="sm:w-72">
                <div class="relative">
                    <select
                        x-model="selectedDistrict"
                        class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                    >
                        <option value="" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">Все федеральные округа</option>
                        @foreach($federalDistricts as $district)
                        <option value="{{ $district }}" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">{{ $district }}</option>
                        @endforeach
                    </select>
                    <span class="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                        <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke="" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                    </span>
                </div>
            </div>
        </div>

        {{-- Быстрые действия --}}
        <div class="flex flex-wrap gap-2 mb-5">
            @foreach($federalDistricts as $district)
            @php
                $districtRegionIds = $regions->where('federal_district', $district)->pluck('id')->toArray();
            @endphp
            <button
                type="button"
                @click="
                    const ids = @js($districtRegionIds);
                    const allSelected = ids.every(id => selectedRegions.includes(id));
                    if (allSelected) {
                        selectedRegions = selectedRegions.filter(id => !ids.includes(id));
                    } else {
                        selectedRegions = [...new Set([...selectedRegions, ...ids])];
                    }
                "
                class="px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 hover:bg-gray-100 dark:border-gray-600 dark:hover:bg-gray-700 transition text-gray-700 dark:text-gray-300"
            >
                {{ $district }}
            </button>
            @endforeach
            <button
                type="button"
                @click="selectedRegions = @js($regions->pluck('id')->toArray())"
                class="px-3 py-1.5 text-xs font-medium rounded-lg border border-green-300 text-green-700 hover:bg-green-50 dark:border-green-700 dark:text-green-400 dark:hover:bg-green-900/30 transition"
            >
                Выбрать все
            </button>
            <button
                type="button"
                @click="selectedRegions = []; primaryRegion = null"
                class="px-3 py-1.5 text-xs font-medium rounded-lg border border-red-300 text-red-700 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-900/30 transition"
            >
                Очистить
            </button>
        </div>

        {{-- Выбранные регионы --}}
        <div class="mb-4 px-4 py-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Выбрано регионов: <span class="font-semibold text-gray-900 dark:text-white" x-text="selectedRegions.length"></span>
            </p>
        </div>

        {{-- Список регионов --}}
        <div class="max-h-[500px] overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-xl p-4 bg-white dark:bg-gray-800/30">
            @foreach($regions->groupBy('federal_district') as $district => $districtRegions)
            <template x-if="selectedDistrict === '' || selectedDistrict === '{{ $district }}'">
                <div class="mb-6 last:mb-0">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 sticky top-0 bg-white dark:bg-gray-800/30 py-2">{{ $district }} федеральный округ</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                        @foreach($districtRegions as $region)
                        <template x-if="search === '' || '{{ mb_strtolower($region->name) }}'.includes(search.toLowerCase())">
                            <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer transition has-[:checked]:border-[#c3242a] has-[:checked]:bg-red-50 dark:has-[:checked]:bg-red-900/20">
                                <input
                                    type="checkbox"
                                    name="regions[]"
                                    value="{{ $region->id }}"
                                    x-model="selectedRegions"
                                    :value="{{ $region->id }}"
                                    class="h-4 w-4 rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]"
                                >
                                <span class="flex-1 text-sm text-gray-700 dark:text-gray-300">{{ $region->name }}</span>
                                <button
                                    type="button"
                                    @click.prevent="primaryRegion = {{ $region->id }}"
                                    x-show="selectedRegions.includes({{ $region->id }})"
                                    :class="primaryRegion === {{ $region->id }} ? 'text-yellow-500' : 'text-gray-300 hover:text-yellow-400'"
                                    title="Сделать основным"
                                    class="transition"
                                >
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                </button>
                            </label>
                        </template>
                        @endforeach
                    </div>
                </div>
            </template>
            @endforeach
        </div>

        <input type="hidden" name="primary_region" :value="primaryRegion">

        {{-- Кнопка сохранения --}}
        <div class="mt-6">
            <button
                type="submit"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#c3242a] text-white text-sm font-medium rounded-lg hover:bg-[#a01e24] transition shadow-theme-xs"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Сохранить изменения
            </button>
        </div>
    </form>
</div>
