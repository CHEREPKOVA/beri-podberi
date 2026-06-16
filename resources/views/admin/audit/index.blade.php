@extends('layouts.app')

@section('title', 'Журнал')
@section('heading', 'Журнал')

@section('content')
@php
    $adminOptions = $staffAdmins
        ->map(fn ($admin) => ['value' => (string) $admin->id, 'label' => $admin->name])
        ->values()
        ->all();

    $sourceOptions = collect($sources)
        ->map(fn ($source, $key) => ['value' => (string) $key, 'label' => $source['label']])
        ->values()
        ->all();

    $moduleOptions = collect($modules)
        ->map(fn ($module, $key) => ['value' => (string) $key, 'label' => $module['label']])
        ->values()
        ->all();

    $statusOptions = [
        ['value' => 'success', 'label' => 'Успешные'],
        ['value' => 'failed', 'label' => 'С ошибкой'],
    ];

    $fieldInputClass = 'w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200 shadow-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent transition-colors';
@endphp

<div class="space-y-6">
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-4 text-sm text-gray-600 dark:text-gray-300">
        <p>Единая лента событий платформы: действия в админ-панели, партнёрства производителей и дистрибьюторов, изменения товаров.</p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Сотрудник</label>
                    @include('admin.partials.styled_select', [
                        'name' => 'admin_id',
                        'value' => request('admin_id'),
                        'placeholder' => 'Все',
                        'options' => $adminOptions,
                    ])
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Источник</label>
                    @include('admin.partials.styled_select', [
                        'name' => 'source',
                        'value' => request('source'),
                        'placeholder' => 'Все',
                        'options' => $sourceOptions,
                    ])
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Модуль</label>
                    @include('admin.partials.styled_select', [
                        'name' => 'module',
                        'value' => request('module'),
                        'placeholder' => 'Все',
                        'options' => $moduleOptions,
                    ])
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Компания</label>
                    <input type="text" name="company_name" value="{{ request('company_name') }}"
                           placeholder="Название компании"
                           class="{{ $fieldInputClass }}" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Результат</label>
                    @include('admin.partials.styled_select', [
                        'name' => 'status',
                        'value' => request('status'),
                        'placeholder' => 'Все',
                        'options' => $statusOptions,
                    ])
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Дата с</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}"
                           class="{{ $fieldInputClass }}" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Дата по</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}"
                           class="{{ $fieldInputClass }}" />
                </div>
                <div class="md:col-span-2 xl:col-span-4 flex items-center gap-2 pt-1">
                    <button type="submit" class="px-4 py-2.5 bg-[#c3242a] text-white rounded-lg text-sm font-medium hover:bg-[#a01e24] transition-colors">
                        Применить
                    </button>
                    @if(request()->hasAny(['admin_id', 'source', 'module', 'company_name', 'company_type', 'action', 'status', 'date_from', 'date_to']))
                        <a href="{{ route('admin.audit.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">Сбросить</a>
                    @endif
                </div>
            </form>
        </div>

        <div class="p-6 space-y-3">
            @include('admin.audit._entries', [
                'events' => $events,
                'permissionLabels' => $permissionLabels,
                'compact' => false,
                'linkToShow' => true,
            ])
        </div>

        @if($events->hasPages())
            <div class="px-6 pb-6 pt-2 border-t border-gray-200 dark:border-gray-700">
                {{ $events->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
