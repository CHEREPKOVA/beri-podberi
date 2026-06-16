@props([
    'events',
    'permissionLabels' => [],
    'compact' => false,
    'linkToShow' => true,
])

@forelse($events as $event)
    @php
        $status = (int) ($event['response_status'] ?? 0);
        $statusLabel = $status >= 400 ? 'Ошибка' : ($status >= 200 ? 'Успешно' : '—');
        $statusClass = $status >= 400
            ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'
            : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300';
        $permissionLabel = ! empty($event['required_permission'])
            ? ($permissionLabels[$event['required_permission']] ?? $event['required_permission'])
            : null;
    @endphp
    <div @class([
        'border-b border-gray-100 dark:border-gray-700 pb-3',
        'text-sm' => $compact,
    ])>
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0 flex-1">
                <div class="font-medium text-gray-900 dark:text-white">
                    {{ $event['title'] }}
                </div>
                @if(! empty($event['subtitle']))
                    <div class="mt-0.5 text-xs text-gray-600 dark:text-gray-300">{{ $event['subtitle'] }}</div>
                @endif
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ $event['occurred_at_label'] }}
                    — {{ $event['actor_name'] }}
                    @if(! $compact && ! empty($event['module']))
                        <span class="mx-1">·</span>{{ $event['module'] }}
                    @endif
                    @if(! $compact && ! empty($event['source_label']))
                        <span class="mx-1">·</span>{{ $event['source_label'] }}
                    @endif
                </div>
                @if(! $compact && (! empty($event['company_name']) || $permissionLabel))
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        @if(! empty($event['company_name']))
                            Компания: {{ $event['company_name'] }}
                        @endif
                        @if($permissionLabel)
                            @if(! empty($event['company_name']))<span class="mx-1">·</span>@endif
                            Право: {{ $permissionLabel }}
                        @endif
                    </div>
                @endif
            </div>
            <div class="flex items-center gap-2 shrink-0">
                @if($status > 0)
                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $statusClass }}">
                        {{ $statusLabel }}@if($event['source'] === 'admin_action') ({{ $status }})@endif
                    </span>
                @endif
                @if($linkToShow && auth()->user()?->hasPermission('audit.view'))
                    <a href="{{ route('admin.audit.show', ['source' => $event['source'], 'id' => $event['source_id']]) }}"
                       class="text-xs text-[#c3242a] hover:underline whitespace-nowrap">
                        Подробнее
                    </a>
                @endif
            </div>
        </div>
    </div>
@empty
    <div class="text-sm text-gray-500 dark:text-gray-400">Событий пока нет.</div>
@endforelse
