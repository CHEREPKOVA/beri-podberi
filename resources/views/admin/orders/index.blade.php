@extends('layouts.app')

@section('title', 'Мониторинг заказов')
@section('heading', 'Мониторинг и модерация заказов')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
@endpush

@section('content')
@php
    $problem = request('problem');
    $filterChip = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors';
    $filterChipActive = 'border-[#c3242a] bg-red-50 text-[#c3242a] dark:bg-red-900/20 dark:text-red-300';
    $filterChipIdle = 'border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50';
    $inputClass = 'w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200 shadow-sm focus:outline-none focus:ring-2 focus:ring-[#c3242a] focus:border-transparent transition-colors hover:border-[#c3242a]';
    $selectClass = $inputClass.' appearance-none pl-3 pr-9 cursor-pointer';
@endphp
<div class="space-y-6">
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.orders.index', request()->except('problem')) }}"
           class="{{ $filterChip }} {{ blank($problem) ? $filterChipActive : $filterChipIdle }}">
            Все <span class="opacity-70">{{ $counts['all'] }}</span>
        </a>
        <a href="{{ route('admin.orders.index', array_merge(request()->except('page'), ['problem' => 'all'])) }}"
           class="{{ $filterChip }} {{ $problem === 'all' ? $filterChipActive : $filterChipIdle }}">
            Требуют внимания <span class="opacity-70">{{ $counts['problematic'] }}</span>
        </a>
        <a href="{{ route('admin.orders.index', array_merge(request()->except('page'), ['problem' => 'stuck'])) }}"
           class="{{ $filterChip }} {{ $problem === 'stuck' ? $filterChipActive : $filterChipIdle }}">
            Без движения <span class="opacity-70">{{ $counts['stuck'] }}</span>
        </a>
        <a href="{{ route('admin.orders.index', array_merge(request()->except('page'), ['problem' => 'paused'])) }}"
           class="{{ $filterChip }} {{ $problem === 'paused' ? $filterChipActive : $filterChipIdle }}">
            Приостановлены <span class="opacity-70">{{ $counts['paused'] }}</span>
        </a>
        <a href="{{ route('admin.orders.index', array_merge(request()->except('page'), ['problem' => 'active_claim'])) }}"
           class="{{ $filterChip }} {{ $problem === 'active_claim' ? $filterChipActive : $filterChipIdle }}">
            Претензии <span class="opacity-70">{{ $counts['claims'] }}</span>
        </a>
        <a href="{{ route('admin.orders.index', array_merge(request()->except('page'), ['problem' => 'integration_error'])) }}"
           class="{{ $filterChip }} {{ $problem === 'integration_error' ? $filterChipActive : $filterChipIdle }}">
            Ошибки интеграции <span class="opacity-70">{{ $counts['integration'] }}</span>
        </a>
        <a href="{{ route('admin.orders.index', array_merge(request()->except('page'), ['problem' => 'rejected_without_reason'])) }}"
           class="{{ $filterChip }} {{ $problem === 'rejected_without_reason' ? $filterChipActive : $filterChipIdle }}">
            Без причины <span class="opacity-70">{{ $counts['rejected_without_reason'] }}</span>
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <form method="GET" class="flex flex-wrap items-end gap-3">
                @if(request('problem'))
                    <input type="hidden" name="problem" value="{{ request('problem') }}">
                @endif

                <div>
                    <label class="block text-xs text-gray-500 mb-1">Поиск</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Номер или компания..."
                           class="{{ $inputClass }} w-64">
                </div>

                <div>
                    <label class="block text-xs text-gray-500 mb-1">Статус</label>
                    <div class="relative min-w-[12rem]">
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

                <button type="submit" class="px-4 py-2 bg-white dark:bg-gray-800 border border-[#c3242a] text-[#c3242a] dark:text-red-400 dark:border-red-400 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 text-sm font-medium transition-colors">
                    Применить
                </button>
                @if(request()->hasAny(['search', 'status', 'date_from', 'date_to', 'problem']))
                    <a href="{{ route('admin.orders.index') }}" class="text-sm text-gray-500 py-2">Сбросить</a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Номер</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Дата</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Покупатель</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Поставщик</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Статус</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Сумма</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Внимание</th>
                        <th class="px-4 py-3 w-28"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($orders as $order)
                    @php
                        $flags = $order->problem_flags ?? [];
                        $rowHighlight = $flags !== []
                            ? 'bg-red-50/40 dark:bg-red-900/10'
                            : '';
                    @endphp
                    <tr class="group hover:bg-gray-50 dark:hover:bg-gray-700/50 {{ $rowHighlight }}">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.orders.show', $order) }}" class="text-sm font-medium text-[#c3242a] hover:underline">
                                {{ $order->order_number }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap">
                            {{ optional($order->ordered_at)->format('d.m.Y H:i') ?: '—' }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                            {{ $order->endCompanyProfile?->displayName() ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                            {{ $order->distributorProfile?->displayName() ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded-md text-xs font-medium {{ $order->statusBadgeClass() }}">
                                {{ $order->statusLabel() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white whitespace-nowrap">
                            {{ number_format((float) $order->total_amount, 2, ',', ' ') }} ₽
                        </td>
                        <td class="px-4 py-3">
                            @if($flags === [])
                                <span class="text-xs text-gray-400">—</span>
                            @else
                                <div class="flex flex-wrap gap-1">
                                    @foreach($flags as $flag)
                                        <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-medium bg-[#c3242a]/10 text-[#a01e24] ring-1 ring-[#c3242a]/25">
                                            {{ $order->problemFlagLabel($flag) }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex items-center justify-end min-h-[2rem] opacity-0 pointer-events-none transition-opacity group-hover:opacity-100 group-hover:pointer-events-auto group-focus-within:opacity-100 group-focus-within:pointer-events-auto">
                                <a href="{{ route('admin.orders.show', $order) }}"
                                   class="text-sm text-[#c3242a] hover:underline">Открыть</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-sm text-gray-500">
                            Заказы не найдены
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($orders->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                {{ $orders->links() }}
            </div>
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
