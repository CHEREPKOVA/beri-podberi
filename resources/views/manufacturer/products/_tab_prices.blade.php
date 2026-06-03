@php
    $basePriceValue = old('base_price', $product?->base_price);
    $customRegionalCount = $product?->regionalPrices->count() ?? 0;
    $regionalCustomFlags = $regions->mapWithKeys(function ($region) use ($product) {
        $regionalPrice = $product?->regionalPrices->firstWhere('region_id', $region->id);
        $fieldValue = old('regional_prices.' . $region->id, $regionalPrice?->price);

        return [$region->id => $fieldValue !== null && $fieldValue !== ''];
    });
@endphp

<div class="space-y-8">
    <div>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Базовая цена</h3>
        <div class="max-w-md">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Цена без скидок (₽)
            </label>
            <input type="number" name="base_price" value="{{ $basePriceValue }}"
                step="0.01" min="0"
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
            @if($product?->price_updated_at)
            <p class="mt-1 text-xs text-gray-500">Последнее обновление: {{ $product->price_updated_at->format('d.m.Y H:i') }}</p>
            @endif
        </div>
    </div>

    <div
        x-data="{
            basePrice: @js($basePriceValue !== null && $basePriceValue !== '' ? (string) $basePriceValue : ''),
            customRegions: @js($regionalCustomFlags),
            formatRub(v) {
                if (v === '' || v === null || v === undefined) return '—';
                const n = parseFloat(String(v).replace(',', '.'));
                if (Number.isNaN(n)) return '—';
                return n.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₽';
            },
            setRegionCustom(regionId, isCustom) {
                this.customRegions[regionId] = isCustom;
            },
            isRegionCustom(regionId) {
                return !!this.customRegions[regionId];
            },
            clearAllRegional() {
                this.$refs.regionalTable?.querySelectorAll('[data-regional-price]').forEach((input) => {
                    input.value = '';
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                });
            },
            fillEmptyWithBase() {
                if (!this.basePrice) return;
                this.$refs.regionalTable?.querySelectorAll('[data-regional-price]').forEach((input) => {
                    if (input.value === '') {
                        input.value = this.basePrice;
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                });
            },
        }"
        @input="if ($event.target.name === 'base_price') basePrice = $event.target.value"
    >
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-4">
            <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Цены по регионам</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 max-w-2xl">
                    Для каждого региона из вашего профиля можно задать отдельную цену.
                    Пустое поле — для региона действует базовая цена выше.
                </p>
            </div>
            @if($regions->isNotEmpty())
            <div class="flex flex-wrap gap-2 shrink-0">
                <button type="button" @click="fillEmptyWithBase()"
                    :disabled="!basePrice"
                    :class="!basePrice ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-100 dark:hover:bg-gray-600'"
                    class="text-sm px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 transition-colors">
                    Подставить базовую в пустые
                </button>
                <button type="button" @click="clearAllRegional()"
                    class="text-sm px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                    Сбросить все
                </button>
            </div>
            @endif
        </div>

        @if($regions->isEmpty())
        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 text-yellow-800 dark:text-yellow-200 px-4 py-3 rounded-lg">
            <p>
                В профиле не выбраны регионы присутствия — региональные цены недоступны.
                <a href="{{ route('manufacturer.profile', ['tab' => 'regions']) }}" class="underline font-medium hover:no-underline">Укажите регионы в профиле</a>,
                затем вернитесь к этой вкладке.
            </p>
        </div>
        @else
        <div class="mb-3 flex flex-wrap items-center gap-2 text-sm">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                Регионов в профиле: {{ $regions->count() }}
            </span>
            @if($customRegionalCount > 0)
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-[#c3242a]/10 text-[#c3242a]">
                С индивидуальной ценой: {{ $customRegionalCount }}
            </span>
            @endif
            <span class="text-gray-500 dark:text-gray-400" x-show="basePrice">
                Базовая сейчас: <span class="font-medium text-gray-700 dark:text-gray-300" x-text="formatRub(basePrice)"></span>
            </span>
        </div>

        <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg" x-ref="regionalTable">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Регион</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide min-w-[9rem]">Цена (₽)</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide min-w-[7rem] whitespace-nowrap">Применится</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($regions as $region)
                    @php
                        $regionalPrice = $product?->regionalPrices->firstWhere('region_id', $region->id);
                        $fieldValue = old('regional_prices.' . $region->id, $regionalPrice?->price);
                    @endphp
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">
                            {{ $region->name }}
                        </td>
                        <td class="px-4 py-3 min-w-[9rem]">
                            <input
                                type="number"
                                name="regional_prices[{{ $region->id }}]"
                                data-regional-price
                                data-region-id="{{ $region->id }}"
                                value="{{ $fieldValue }}"
                                step="0.01"
                                min="0"
                                @input="setRegionCustom({{ $region->id }}, $event.target.value !== '')"
                                :placeholder="basePrice || 'пусто'"
                                :title="basePrice ? 'Пустое поле — будет ' + formatRub(basePrice) : 'Пустое поле — базовая цена'"
                                class="w-full min-w-[8rem] px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent"
                            />
                        </td>
                        <td class="px-4 py-3 text-sm whitespace-nowrap">
                            <span x-show="isRegionCustom({{ $region->id }})" x-cloak class="inline-flex items-center whitespace-nowrap px-2 py-0.5 rounded text-xs font-medium bg-[#c3242a]/10 text-[#c3242a]">
                                Своя
                            </span>
                            <span x-show="!isRegionCustom({{ $region->id }})" x-cloak class="inline-flex items-center whitespace-nowrap px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400">
                                Базовая
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            Чтобы убрать индивидуальную цену для региона, очистите поле и нажмите «Сохранить».
            Список регионов настраивается в
            <a href="{{ route('manufacturer.profile', ['tab' => 'regions']) }}" class="text-[#c3242a] hover:underline">профиле компании → Регионы присутствия</a>.
        </p>
        @endif
    </div>

    <div>
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Остатки на складах</h3>
            @if($product?->isSynced())
            <span class="inline-flex items-center text-sm text-blue-600 bg-blue-50 px-3 py-1 rounded-full">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Синхронизируется с {{ $product->sync_source }}
            </span>
            @endif
        </div>

        @if($warehouses->isEmpty())
        <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded-lg">
            <p>У вас нет активных складов. <a href="{{ route('manufacturer.warehouses.index') }}" class="underline hover:no-underline">Добавьте склад</a> в разделе «Склады».</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Склад</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Регион</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Количество</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Резерв</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Доступно</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($warehouses as $warehouse)
                    @php
                        $stock = $product?->stocks->firstWhere('warehouse_id', $warehouse->id);
                    @endphp
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">
                            {{ $warehouse->name }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                            {{ $warehouse->region?->name ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <input type="number" name="stocks[{{ $warehouse->id }}][quantity]"
                                value="{{ old('stocks.' . $warehouse->id . '.quantity', $stock?->quantity ?? 0) }}"
                                min="0" step="1"
                                class="w-24 px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                            {{ $stock?->reserved ?? 0 }}
                        </td>
                        <td class="px-4 py-3 text-sm font-medium {{ ($stock?->available_quantity ?? 0) > 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $stock?->available_quantity ?? 0 }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <td colspan="2" class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">Итого</td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">
                            {{ $product?->stocks->sum('quantity') ?? 0 }}
                        </td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">
                            {{ $product?->stocks->sum('reserved') ?? 0 }}
                        </td>
                        <td class="px-4 py-3 text-sm font-medium {{ ($product?->available_stock ?? 0) > 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $product?->available_stock ?? 0 }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            Остатки указываются отдельно по каждому складу. После изменения нажмите «Сохранить».
            Список складов настраивается в
            <a href="{{ route('manufacturer.warehouses.index') }}" class="text-[#c3242a] hover:underline">разделе «Склады»</a>.
        </p>
        @endif
    </div>
</div>
