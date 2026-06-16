@extends('layouts.app')

@section('title', 'Событие журнала')
@section('heading', 'Событие журнала')

@section('content')
@php
    $payload = (array) ($event['payload'] ?? []);
    $status = (int) ($event['response_status'] ?? 0);
    $permissionLabel = ! empty($event['required_permission'])
        ? ($permissionLabels[$event['required_permission']] ?? $event['required_permission'])
        : null;
    $context = (array) ($payload['context'] ?? []);
    $input = (array) ($context['input'] ?? []);
    $meta = (array) ($payload['meta'] ?? []);
@endphp

<div class="space-y-6">
    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('admin.audit.index') }}"
       class="inline-flex text-sm text-gray-500 hover:text-[#c3242a]">← К журналу</a>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 space-y-6">
        <div>
            <div class="flex flex-wrap items-center gap-2 mb-1">
                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                    {{ $event['source_label'] }}
                </span>
                @if(! empty($event['module']))
                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-red-50 text-[#c3242a] dark:bg-red-900/20 dark:text-red-300">
                        {{ $event['module'] }}
                    </span>
                @endif
            </div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $event['title'] }}</h2>
            @if(! empty($event['subtitle']))
                <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">{{ $event['subtitle'] }}</p>
            @endif
            <p class="text-sm text-gray-500 mt-1">{{ $event['occurred_at_label'] }}</p>
        </div>

        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-gray-500">Инициатор</dt>
                <dd class="font-medium text-gray-900 dark:text-white">{{ $event['actor_name'] }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Источник</dt>
                <dd class="font-medium text-gray-900 dark:text-white">{{ $event['source_label'] }}</dd>
            </div>
            @if($permissionLabel)
                <div>
                    <dt class="text-gray-500">Требуемое право</dt>
                    <dd class="font-medium text-gray-900 dark:text-white">{{ $permissionLabel }}</dd>
                </div>
            @endif
            @if($event['source'] === 'admin_action')
                <div>
                    <dt class="text-gray-500">HTTP-статус</dt>
                    <dd class="font-medium text-gray-900 dark:text-white">{{ $status > 0 ? $status : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Маршрут</dt>
                    <dd class="font-mono text-xs text-gray-800 dark:text-gray-200 break-all">{{ $payload['action'] ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Метод / путь</dt>
                    <dd class="font-mono text-xs text-gray-800 dark:text-gray-200 break-all">
                        {{ $context['method'] ?? '—' }} {{ $context['path'] ?? '' }}
                    </dd>
                </div>
            @endif
            @if(! empty($event['company_name']))
                <div class="md:col-span-2">
                    <dt class="text-gray-500">Компания / участники</dt>
                    <dd class="font-medium text-gray-900 dark:text-white">{{ $event['company_name'] }}</dd>
                </div>
            @endif
            @if($event['source'] === 'admin_action' && ! empty($context['ip']))
                <div>
                    <dt class="text-gray-500">IP</dt>
                    <dd class="font-medium text-gray-900 dark:text-white">{{ $context['ip'] }}</dd>
                </div>
            @endif
        </dl>

        @if($input !== [])
            <div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Переданные данные</h3>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full text-sm">
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($input as $key => $value)
                                <tr>
                                    <td class="px-4 py-2 font-medium text-gray-600 dark:text-gray-300 align-top w-1/3">{{ $key }}</td>
                                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100 break-all">
                                        @if(is_array($value))
                                            <pre class="text-xs whitespace-pre-wrap">{{ json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                                        @else
                                            {{ is_bool($value) ? ($value ? 'да' : 'нет') : (string) $value }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if($meta !== [])
            <div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">Дополнительные данные</h3>
                <pre class="text-xs bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-lg p-4 overflow-x-auto">{{ json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
            </div>
        @endif

        @if($event['source'] === 'admin_action' && ! empty($context['route_params']))
            <div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">Параметры маршрута</h3>
                <pre class="text-xs bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-lg p-4 overflow-x-auto">{{ json_encode($context['route_params'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
            </div>
        @endif
    </div>
</div>
@endsection
