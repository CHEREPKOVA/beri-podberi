@extends('layouts.app')

@section('title', 'Заказы')
@section('heading', 'Заказы')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
@endpush

@section('content')
@php
    use App\Models\PlatformOrder;

    $inputClass = 'w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200 shadow-sm focus:outline-none focus:ring-2 focus:ring-[#c3242a] focus:border-transparent transition-colors hover:border-[#c3242a]';
    $selectClass = $inputClass.' appearance-none pl-3 pr-9 cursor-pointer';
    $canEditStatuses = [
        PlatformOrder::STATUS_NEW,
        PlatformOrder::STATUS_AWAITING_CONFIRMATION,
        PlatformOrder::STATUS_CONFIRMED,
    ];
    $exportQuery = request()->except('page');
    $filterKeys = [
        'search', 'number', 'company', 'status', 'client_type', 'region_id',
        'amount_from', 'amount_to', 'date_from', 'date_to',
        'responsible_contact_id', 'mine', 'attention',
    ];
    $sort = request('sort', 'ordered_at');
    if (! in_array($sort, ['ordered_at', 'total_amount', 'updated'], true)) {
        $sort = 'ordered_at';
    }
    $dir = request('dir', 'desc') === 'asc' ? 'asc' : 'desc';
    $sortQuery = fn (string $column, string $direction) => route(
        'manufacturer.orders.index',
        array_merge(request()->except('page'), ['sort' => $column, 'dir' => $direction])
    );
    $headerSortUrl = function (string $column) use ($sort, $dir, $sortQuery) {
        $nextDir = ($sort === $column && $dir === 'desc') ? 'asc' : 'desc';

        return $sortQuery($column, $nextDir);
    };
    $sortIcon = function (string $column) use ($sort, $dir) {
        if ($sort !== $column) {
            return '<span class="text-gray-300 dark:text-gray-600 ml-0.5" aria-hidden="true">↕</span>';
        }

        return $dir === 'asc'
            ? '<span class="text-[#c3242a] ml-0.5" aria-hidden="true">↑</span>'
            : '<span class="text-[#c3242a] ml-0.5" aria-hidden="true">↓</span>';
    };
    $sortFields = [
        'ordered_at' => 'Дата создания',
        'total_amount' => 'Сумма',
        'updated' => 'Обновление',
    ];
    $dirLabels = [
        'ordered_at' => ['desc' => 'Сначала новые', 'asc' => 'Сначала старые'],
        'total_amount' => ['desc' => 'Сначала крупные', 'asc' => 'Сначала мелкие'],
        'updated' => ['desc' => 'Сначала недавние', 'asc' => 'Сначала давние'],
    ];
    $dirLabel = $dirLabels[$sort][$dir];
    $nextDir = $dir === 'desc' ? 'asc' : 'desc';
    $resetFiltersUrl = route('manufacturer.orders.index', array_filter([
        'sort' => request('sort'),
        'dir' => request('dir'),
    ]));
@endphp
<div class="space-y-6">
    @if(session('success'))
        <div class="rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-900 dark:border-green-700 dark:bg-green-900/20 dark:text-green-100">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="p-5 border-b border-gray-200 dark:border-gray-700">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <h2 class="text-sm font-medium text-gray-700 dark:text-gray-300">Фильтры</h2>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('manufacturer.orders.export', array_merge($exportQuery, ['format' => 'csv'])) }}"
                       class="inline-flex items-center h-9 px-3 rounded-lg text-sm border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                        Экспорт CSV
                    </a>
                    <a href="{{ route('manufacturer.orders.export', array_merge($exportQuery, ['format' => 'xlsx'])) }}"
                       class="inline-flex items-center h-9 px-3 rounded-lg text-sm border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                        Экспорт XLSX
                    </a>
                </div>
            </div>

            <form method="GET" action="{{ route('manufacturer.orders.index') }}" class="flex flex-wrap items-end gap-3">
                @if(request('sort'))
                    <input type="hidden" name="sort" value="{{ $sort }}">
                @endif
                @if(request('dir'))
                    <input type="hidden" name="dir" value="{{ $dir }}">
                @endif
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Поиск</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Номер, компания, товар"
                           class="{{ $inputClass }} w-48">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Номер</label>
                    <input type="text" name="number" value="{{ request('number') }}" placeholder="ORD-..."
                           class="{{ $inputClass }} w-40">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Компания</label>
                    <input type="text" name="company" value="{{ request('company') }}" placeholder="Название / ИНН"
                           class="{{ $inputClass }} w-48">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Статус</label>
                    <div class="relative min-w-[11rem]">
                        <select name="status" class="{{ $selectClass }}">
                            <option value="">Все статусы</option>
                            @foreach($statusLabels as $slug => $label)
                                <option value="{{ $slug }}" @selected(request('status') === $slug)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <svg class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Тип клиента</label>
                    <div class="relative min-w-[10rem]">
                        <select name="client_type" class="{{ $selectClass }}">
                            <option value="">Все</option>
                            <option value="end_company" @selected(request('client_type') === 'end_company')>Конечная компания</option>
                            <option value="distributor" @selected(request('client_type') === 'distributor')>Дистрибьютор</option>
                        </select>
                        <svg class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Регион</label>
                    <div class="relative min-w-[10rem]">
                        <select name="region_id" class="{{ $selectClass }}">
                            <option value="">Все регионы</option>
                            @foreach($regions as $region)
                                <option value="{{ $region->id }}" @selected((string) request('region_id') === (string) $region->id)>
                                    {{ $region->name }}
                                </option>
                            @endforeach
                        </select>
                        <svg class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Сумма от</label>
                    <input type="number" step="0.01" min="0" name="amount_from" value="{{ request('amount_from') }}"
                           class="{{ $inputClass }} w-28">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Сумма до</label>
                    <input type="number" step="0.01" min="0" name="amount_to" value="{{ request('amount_to') }}"
                           class="{{ $inputClass }} w-28">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">С</label>
                    <div class="relative">
                        <input type="text"
                               name="date_from"
                               value="{{ request('date_from') }}"
                               placeholder="дд.мм.гггг"
                               autocomplete="off"
                               class="js-flatpickr {{ $inputClass }} w-[10.5rem] pr-9">
                        <svg class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">По</label>
                    <div class="relative">
                        <input type="text"
                               name="date_to"
                               value="{{ request('date_to') }}"
                               placeholder="дд.мм.гггг"
                               autocomplete="off"
                               class="js-flatpickr {{ $inputClass }} w-[10.5rem] pr-9">
                        <svg class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Менеджер</label>
                    <div class="relative min-w-[11rem]">
                        <select name="responsible_contact_id" class="{{ $selectClass }}">
                            <option value="">Все менеджеры</option>
                            @foreach($managers as $manager)
                                <option value="{{ $manager->id }}" @selected((string) request('responsible_contact_id') === (string) $manager->id)>
                                    {{ $manager->full_name }}
                                </option>
                            @endforeach
                        </select>
                        <svg class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                </div>
                <label class="inline-flex items-center gap-2.5 h-10 text-sm text-gray-700 dark:text-gray-300 cursor-pointer select-none">
                    <input type="checkbox" name="mine" value="1" @checked(request()->boolean('mine'))
                           class="sr-only peer">
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md border-[1.25px] border-gray-300 bg-white transition-colors pointer-events-none peer-checked:border-[#c3242a] peer-checked:bg-[#c3242a] peer-checked:[&>svg]:opacity-100 peer-focus:ring-2 peer-focus:ring-[#c3242a]/30 dark:border-gray-600 dark:bg-gray-800 dark:peer-checked:border-[#c3242a] dark:peer-checked:bg-[#c3242a]">
                        <svg class="h-3.5 w-3.5 text-white opacity-0 transition-opacity" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                            <path d="M11.6666 3.5L5.24992 9.91667L2.33325 7" stroke="currentColor" stroke-width="1.94437" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    Мои
                </label>
                <label class="inline-flex items-center gap-2.5 h-10 text-sm text-gray-700 dark:text-gray-300 cursor-pointer select-none">
                    <input type="checkbox" name="attention" value="1" @checked(request()->boolean('attention'))
                           class="sr-only peer">
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md border-[1.25px] border-gray-300 bg-white transition-colors pointer-events-none peer-checked:border-[#c3242a] peer-checked:bg-[#c3242a] peer-checked:[&>svg]:opacity-100 peer-focus:ring-2 peer-focus:ring-[#c3242a]/30 dark:border-gray-600 dark:bg-gray-800 dark:peer-checked:border-[#c3242a] dark:peer-checked:bg-[#c3242a]">
                        <svg class="h-3.5 w-3.5 text-white opacity-0 transition-opacity" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                            <path d="M11.6666 3.5L5.24992 9.91667L2.33325 7" stroke="currentColor" stroke-width="1.94437" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    Требуют внимания
                </label>
                <button type="submit"
                        class="h-10 px-4 rounded-lg text-sm font-medium border border-[#c3242a] text-[#c3242a] hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                    Применить
                </button>
                @if(request()->hasAny($filterKeys))
                    <a href="{{ $resetFiltersUrl }}" class="text-sm text-gray-500 py-2 hover:text-[#c3242a]">Сбросить</a>
                @endif
            </form>
        </div>

        <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-900/30">
            <div class="flex flex-wrap items-center gap-3">
                <div class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">
                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l3-3m0 0l3 3m-3-3v12"/>
                    </svg>
                    Сортировка
                </div>
                <div class="inline-flex flex-wrap items-center gap-1.5">
                    @foreach($sortFields as $column => $label)
                        <a href="{{ $sortQuery($column, $dir) }}"
                           class="inline-flex items-center h-8 px-3 rounded-lg text-sm transition-colors
                               {{ $sort === $column
                                   ? 'border border-[#c3242a] bg-red-50 text-[#c3242a] font-medium dark:bg-red-900/20 dark:text-red-300'
                                   : 'border border-gray-200 bg-white text-gray-600 hover:border-gray-300 hover:text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-gray-500' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
                <a href="{{ $sortQuery($sort, $nextDir) }}"
                   class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg text-sm border border-gray-200 bg-white text-gray-700 hover:border-[#c3242a] hover:text-[#c3242a] dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-[#c3242a]"
                   title="Сменить направление">
                    @if($dir === 'desc')
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                        </svg>
                    @else
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                        </svg>
                    @endif
                    {{ $dirLabel }}
                </a>
            </div>
        </div>

        @if($orders->isEmpty())
            <div class="p-8 text-center">
                <p class="text-gray-600 dark:text-gray-300">Заказов по выбранным фильтрам нет</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th class="text-left py-3 px-5">Номер</th>
                            <th class="text-left py-3 px-3">
                                <a href="{{ $headerSortUrl('ordered_at') }}" class="inline-flex items-center hover:text-[#c3242a] {{ $sort === 'ordered_at' ? 'text-[#c3242a] font-medium' : '' }}">
                                    Создан {!! $sortIcon('ordered_at') !!}
                                </a>
                            </th>
                            <th class="text-left py-3 px-3">Клиент</th>
                            <th class="text-left py-3 px-3">Статус</th>
                            <th class="text-left py-3 px-3">
                                <a href="{{ $headerSortUrl('total_amount') }}" class="inline-flex items-center hover:text-[#c3242a] {{ $sort === 'total_amount' ? 'text-[#c3242a] font-medium' : '' }}">
                                    Сумма {!! $sortIcon('total_amount') !!}
                                </a>
                            </th>
                            <th class="text-left py-3 px-3">Менеджер</th>
                            <th class="text-left py-3 px-3">
                                <a href="{{ $headerSortUrl('updated') }}" class="inline-flex items-center hover:text-[#c3242a] {{ $sort === 'updated' ? 'text-[#c3242a] font-medium' : '' }}">
                                    Обновлён {!! $sortIcon('updated') !!}
                                </a>
                            </th>
                            <th class="text-left py-3 px-5">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                            @php
                                $needsAttention = $order->requiresSupplierAttention() || $order->isOverdueUnconfirmed();
                                $rowClass = $needsAttention
                                    ? 'border-b border-[#c3242a]/20 bg-red-50/60 dark:bg-red-900/15'
                                    : 'border-b border-gray-100 dark:border-gray-700/60 hover:bg-gray-50 dark:hover:bg-gray-900/30';
                                $canEdit = in_array($order->status, $canEditStatuses, true);
                                $clientParts = array_filter([
                                    $order->endCompanyProfile?->displayName(),
                                    $order->distributorProfile?->displayName(),
                                ]);
                            @endphp
                            <tr class="{{ $rowClass }} group">
                                <td class="py-3 px-5">
                                    <a href="{{ route('manufacturer.orders.show', $order) }}" class="font-medium text-[#c3242a] hover:underline">
                                        {{ $order->order_number }}
                                    </a>
                                </td>
                                <td class="py-3 px-3 text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                    {{ optional($order->ordered_at)->format('d.m.Y H:i') ?: '—' }}
                                </td>
                                <td class="py-3 px-3 text-gray-900 dark:text-white">
                                    @if($clientParts === [])
                                        —
                                    @else
                                        <span>{{ $order->endCompanyProfile?->displayName() ?? '—' }}</span>
                                        @if($order->distributorProfile)
                                            <p class="text-xs text-gray-500 mt-0.5">через {{ $order->distributorProfile->displayName() }}</p>
                                        @endif
                                    @endif
                                </td>
                                <td class="py-3 px-3">
                                    <span class="px-2 py-1 rounded-md text-xs font-medium {{ $order->statusBadgeClass() }}">
                                        {{ $order->statusLabel() }}
                                    </span>
                                    @if($order->statusDescription())
                                        <p class="text-xs text-gray-500 mt-1 max-w-[12rem]">{{ $order->statusDescription() }}</p>
                                    @endif
                                </td>
                                <td class="py-3 px-3 font-medium text-gray-900 dark:text-white whitespace-nowrap">
                                    {{ number_format((float) $order->total_amount, 2, ',', ' ') }} ₽
                                </td>
                                <td class="py-3 px-3 text-gray-600 dark:text-gray-300">
                                    {{ $order->manufacturerResponsibleContact?->full_name ?? '—' }}
                                </td>
                                <td class="py-3 px-3 text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                    {{ optional($order->lastActivityAt())->format('d.m.Y H:i') ?: '—' }}
                                </td>
                                <td class="py-3 px-5">
                                    <div class="flex items-center gap-1 min-h-[2rem] opacity-0 pointer-events-none transition-opacity group-hover:opacity-100 group-hover:pointer-events-auto group-focus-within:opacity-100 group-focus-within:pointer-events-auto">
                                        <a href="{{ route('manufacturer.orders.show', $order) }}"
                                           class="p-1.5 text-gray-400 hover:text-[#c3242a] rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
                                           title="Открыть">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            <span class="sr-only">Открыть</span>
                                        </a>
                                        @if($canEdit)
                                            <a href="{{ route('manufacturer.orders.show', $order) }}#edit"
                                               class="p-1.5 text-gray-400 hover:text-[#c3242a] rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
                                               title="Изменить">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                                <span class="sr-only">Изменить</span>
                                            </a>
                                        @endif
                                        <a href="{{ route('manufacturer.orders.print', $order) }}"
                                           target="_blank"
                                           class="p-1.5 text-gray-400 hover:text-[#c3242a] rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
                                           title="Печать">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                            </svg>
                                            <span class="sr-only">Печать</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-gray-200 dark:border-gray-700">{{ $orders->links() }}</div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/ru.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const fpLocale = (typeof flatpickr !== 'undefined' && flatpickr.l10ns?.ru) ? flatpickr.l10ns.ru : 'default';

    document.querySelectorAll('.js-flatpickr').forEach((el) => {
        flatpickr(el, {
            locale: fpLocale,
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd.m.Y',
            allowInput: false,
            disableMobile: true,
            altInputClass: el.className.replace(/\bjs-flatpickr\b/g, '').trim(),
        });
    });
});
</script>
@endpush
