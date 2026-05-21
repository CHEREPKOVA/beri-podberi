<div>
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">История изменений</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Последние действия в профиле организации.</p>

    @if($changes->isEmpty())
        <p class="text-sm text-gray-500">Записей пока нет — они появятся после сохранения изменений в профиле.</p>
    @else
        <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-xl">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Дата</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Раздел</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Событие</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Пользователь</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900/40">
                    @foreach($changes as $ch)
                        <tr>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ $ch->created_at->format('d.m.Y H:i') }}</td>
                            <td class="px-4 py-3 text-gray-900 dark:text-white">{{ $ch->section }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $ch->summary }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $ch->user?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
