<div class="space-y-8">
    <div>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Базовая цена</h3>
        <div class="max-w-md">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Цена без скидок (₽)
            </label>
            <input type="number" name="base_price" value="{{ old('base_price', $product?->base_price) }}"
                step="0.01" min="0"
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
            @if($product?->price_updated_at)
            <p class="mt-1 text-xs text-gray-500">Последнее обновление: {{ $product->price_updated_at->format('d.m.Y H:i') }}</p>
            @endif
        </div>
    </div>

    <div x-data="{ showRegionalPrices: {{ $product?->regionalPrices->count() > 0 ? 'true' : 'false' }} }">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Цены по регионам</h3>
            <button type="button" @click="showRegionalPrices = !showRegionalPrices"
                class="text-sm text-[#c3242a] hover:text-[#a01e24]">
                <span x-text="showRegionalPrices ? 'Скрыть' : 'Показать'"></span>
            </button>
        </div>

        <div x-show="showRegionalPrices" x-cloak class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                Укажите цены для конкретных регионов. Если цена не указана, будет использована базовая цена.
            </p>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($regions as $region)
                @php
                    $regionalPrice = $product?->regionalPrices->firstWhere('region_id', $region->id);
                @endphp
                <div class="flex items-center gap-3">
                    <label class="text-sm text-gray-700 dark:text-gray-300 w-32 truncate" title="{{ $region->name }}">
                        {{ $region->name }}
                    </label>
                    <input type="number" name="regional_prices[{{ $region->id }}]"
                        value="{{ old('regional_prices.' . $region->id, $regionalPrice?->price) }}"
                        step="0.01" min="0" placeholder="—"
                        class="flex-1 px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
                </div>
                @endforeach
            </div>
        </div>
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
        @endif
    </div>
</div>
