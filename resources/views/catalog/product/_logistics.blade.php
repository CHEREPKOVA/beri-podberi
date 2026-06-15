@php
    $logistics = $logistics ?? [];
@endphp

<section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Логистические параметры</h2>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                <tr><td class="py-2 pr-4 text-gray-500 dark:text-gray-400 w-1/2">Вес</td><td class="py-2 text-gray-900 dark:text-white">{{ $logistics['weight'] ?? 'Не задан' }}</td></tr>
                <tr><td class="py-2 pr-4 text-gray-500 dark:text-gray-400">Габариты</td><td class="py-2 text-gray-900 dark:text-white">{{ $logistics['dimensions'] ?? 'Не заданы' }}</td></tr>
                <tr><td class="py-2 pr-4 text-gray-500 dark:text-gray-400">Объём / кубатура</td><td class="py-2 text-gray-900 dark:text-white">{{ $logistics['volume'] ?? 'Не задан' }}</td></tr>
                <tr><td class="py-2 pr-4 text-gray-500 dark:text-gray-400">Количество на паллете</td><td class="py-2 text-gray-900 dark:text-white">{{ $logistics['pallet_qty'] ?? 'Не заданы' }}</td></tr>
                <tr><td class="py-2 pr-4 text-gray-500 dark:text-gray-400">Рядность паллет</td><td class="py-2 text-gray-900 dark:text-white">{{ $logistics['pallet_rows'] ?? 'Не задана' }}</td></tr>
                <tr><td class="py-2 pr-4 text-gray-500 dark:text-gray-400">Количество в упаковке</td><td class="py-2 text-gray-900 dark:text-white">{{ $logistics['pack_quantity'] ?? '—' }}</td></tr>
                <tr><td class="py-2 pr-4 text-gray-500 dark:text-gray-400">Требования к упаковке</td><td class="py-2 text-gray-900 dark:text-white">{{ $logistics['packaging'] ?? 'Не заданы' }}</td></tr>
                <tr><td class="py-2 pr-4 text-gray-500 dark:text-gray-400">Кратность / мин. отгрузка</td><td class="py-2 text-gray-900 dark:text-white">{{ $logistics['min_order_quantity'] ?? '—' }}</td></tr>
                <tr><td class="py-2 pr-4 text-gray-500 dark:text-gray-400">Условия отгрузки</td><td class="py-2 text-gray-900 dark:text-white">{{ $logistics['shipping'] ?? 'Не заданы' }}</td></tr>
            </tbody>
        </table>
    </div>
</section>
