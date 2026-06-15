@php
    $steps = $completion['steps'] ?? [];
    $percent = $completion['percent'] ?? 0;
    $completedCount = $completion['completed_count'] ?? 0;
    $totalCount = $completion['total_count'] ?? 0;
    $isComplete = $completion['is_complete'] ?? false;
    $introComplete = $completion['intro_complete'] ?? 'Обязательные шаги выполнены.';
    $introIncomplete = $completion['intro_incomplete'] ?? 'Заполните профиль для работы на платформе.';
    $notice = $completion['notice'] ?? null;
@endphp

<div class="space-y-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Настройка профиля</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {{ $isComplete ? $introComplete : $introIncomplete }}
                </p>
            </div>
            <div class="text-right shrink-0">
                <p class="text-2xl font-bold text-[#c3242a]">{{ $percent }}%</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $completedCount }} из {{ $totalCount }} шагов</p>
            </div>
        </div>

        <div class="mt-4 h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
            <div class="h-full rounded-full bg-[#c3242a] transition-all duration-300" style="width: {{ $percent }}%"></div>
        </div>

        @if($notice)
        <div @class([
            'mt-4 flex items-start gap-3 rounded-lg border px-4 py-3 text-sm',
            'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300' => ($notice['type'] ?? '') === 'warning',
            'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-300' => ($notice['type'] ?? '') !== 'warning',
        ])>
            <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                @if(($notice['type'] ?? '') === 'warning')
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                @else
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                @endif
            </svg>
            <p>{{ $notice['message'] }}</p>
        </div>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach($steps as $step)
        <a href="{{ $step['url'] }}"
           class="group block rounded-xl border p-5 transition hover:shadow-sm
               {{ $step['completed']
                   ? 'border-gray-300 bg-gray-50 dark:border-gray-600 dark:bg-gray-800/80 hover:border-gray-400'
                   : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 hover:border-[#c3242a]/40' }}">
            <div class="flex items-start justify-between gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg
                    {{ $step['completed'] ? 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }}">
                    @if($step['completed'])
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    @else
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    @endif
                </div>
                @if($step['required'])
                <span class="text-[10px] font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full
                    {{ $step['completed'] ? 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300' : 'bg-red-50 text-[#c3242a] dark:bg-red-900/20 dark:text-red-400' }}">
                    Обязательно
                </span>
                @endif
            </div>
            <h3 class="mt-4 text-sm font-semibold text-gray-900 dark:text-white group-hover:text-[#c3242a] transition-colors">
                {{ $step['title'] }}
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $step['description'] }}</p>
            <p class="mt-3 text-xs font-medium text-[#c3242a]">
                {{ $step['completed'] ? 'Выполнено' : 'Перейти к заполнению →' }}
            </p>
        </a>
        @endforeach
    </div>
</div>
