<section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Наличие у поставщиков</h2>
    @if($companyRegionName ?? null)
        <p class="text-sm text-gray-500 mb-3">Регион компании: {{ $companyRegionName }}</p>
    @endif
    <template x-if="live.warehouse_stock_rows.length">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="text-left py-2 pr-4">Поставщик</th>
                        <th class="text-left py-2 pr-4">Склад</th>
                        <th class="text-left py-2 pr-4">Регион</th>
                        <th class="text-left py-2 pr-4">Наличие</th>
                        <th class="text-left py-2 pr-4">Мин. партия</th>
                        <th class="text-left py-2 pr-4">Цена</th>
                        <th class="text-left py-2 pr-4">Обновлено</th>
                        <th class="text-left py-2">Условия</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(row, index) in live.warehouse_stock_rows" :key="index">
                        <tr class="border-b border-gray-100 dark:border-gray-700/60">
                            <td class="py-2 pr-4 text-gray-900 dark:text-white" x-text="row.distributor_name"></td>
                            <td class="py-2 pr-4 text-gray-900 dark:text-white" x-text="row.warehouse_name"></td>
                            <td class="py-2 pr-4 text-gray-600 dark:text-gray-300" x-text="row.region_name"></td>
                            <td class="py-2 pr-4" :class="row.available_quantity > 0 ? 'text-green-600' : 'text-amber-600'">
                                <span x-text="row.available_quantity"></span>
                                <span x-show="row.status_note" class="block text-[10px] text-amber-700" x-text="row.status_note"></span>
                            </td>
                            <td class="py-2 pr-4 text-gray-700 dark:text-gray-300" x-text="row.min_order_quantity"></td>
                            <td class="py-2 pr-4 text-gray-700 dark:text-gray-300" x-text="row.retail_price_formatted"></td>
                            <td class="py-2 pr-4 text-gray-700 dark:text-gray-300" x-text="row.stock_updated_at_formatted"></td>
                            <td class="py-2 text-gray-700 dark:text-gray-300" x-text="row.shipping_conditions"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </template>
    <p x-show="!live.warehouse_stock_rows.length" class="text-sm text-gray-500 dark:text-gray-400">
        @if(in_array($cardRole ?? '', ['end_company', 'distributor'], true))
            Нет складских остатков в вашем регионе. Товар может быть доступен под заказ при наличии цены.
        @else
            Складские остатки не заполнены.
        @endif
    </p>
</section>
