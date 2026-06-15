<section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Информация о поставщиках</h2>
    <template x-if="live.supplier_rows.length">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="text-left py-2 pr-4">Дистрибьютор</th>
                        <th class="text-left py-2 pr-4">Цена</th>
                        <th class="text-left py-2 pr-4">Остаток</th>
                        <th class="text-left py-2 pr-4">Условия поставки</th>
                        <th class="text-left py-2">Региональность</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(row, index) in live.supplier_rows" :key="row.distributor_product_id ?? row.name ?? index">
                        <tr class="border-b border-gray-100 dark:border-gray-700/60">
                            <td class="py-2 pr-4 text-gray-900 dark:text-white" x-text="row.name"></td>
                            <td class="py-2 pr-4 text-gray-900 dark:text-white" x-text="row.price_formatted"></td>
                            <td class="py-2 pr-4" :class="row.stock > 0 ? 'text-green-600' : 'text-amber-600'" x-text="row.stock"></td>
                            <td class="py-2 pr-4 text-gray-700 dark:text-gray-300" x-text="row.conditions"></td>
                            <td class="py-2 text-gray-700 dark:text-gray-300" x-text="row.regions"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </template>
    <p x-show="!live.supplier_rows.length" class="text-sm text-gray-500 dark:text-gray-400">Поставщики для вашего региона не найдены.</p>
</section>
