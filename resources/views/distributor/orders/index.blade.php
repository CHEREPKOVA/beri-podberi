@extends('layouts.app')

@section('title', 'Мои заказы')
@section('heading', 'Мои заказы')

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
                    <a href="{{ route('distributor.orders.export', array_merge($exportQuery, ['format' => 'csv'])) }}"
                       class="inline-flex items-center h-9 px-3 rounded-lg text-sm border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                        Экспорт CSV
                    </a>
                    <a href="{{ route('distributor.orders.export', array_merge($exportQuery, ['format' => 'xlsx'])) }}"
                       class="inline-flex items-center h-9 px-3 rounded-lg text-sm border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                        Экспорт XLSX
                    </a>
                </div>
            </div>

            <form method="GET" action="{{ route('distributor.orders.index') }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Номер</label>
                    <input type="text" name="number" value="{{ request('number') }}" placeholder="ORD-..."
                           class="{{ $inputClass }} w-40">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Покупатель</label>
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
                @if(request()->hasAny(['number', 'company', 'status', 'responsible_contact_id', 'date_from', 'date_to', 'attention']))
                    <a href="{{ route('distributor.orders.index') }}" class="text-sm text-gray-500 py-2 hover:text-[#c3242a]">Сбросить</a>
                @endif
            </form>
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
                            <th class="text-left py-3 px-3">Покупатель</th>
                            <th class="text-left py-3 px-3">Создан</th>
                            <th class="text-left py-3 px-3">Обновлён</th>
                            <th class="text-left py-3 px-3">Сумма</th>
                            <th class="text-left py-3 px-3">Статус</th>
                            <th class="text-left py-3 px-3">Менеджер</th>
                            <th class="text-left py-3 px-5">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                            @php
                                $needsAttention = $order->requiresSupplierAttention();
                                $rowClass = $needsAttention
                                    ? 'border-b border-[#c3242a]/20 bg-red-50/60 dark:bg-red-900/15'
                                    : 'border-b border-gray-100 dark:border-gray-700/60 hover:bg-gray-50 dark:hover:bg-gray-900/30';
                                $canEdit = in_array($order->status, $canEditStatuses, true);
                            @endphp
                            <tr class="{{ $rowClass }} group">
                                <td class="py-3 px-5">
                                    <a href="{{ route('distributor.orders.show', $order) }}" class="font-medium text-[#c3242a] hover:underline">
                                        {{ $order->order_number }}
                                    </a>
                                </td>
                                <td class="py-3 px-3 text-gray-900 dark:text-white">{{ $order->endCompanyProfile?->displayName() ?? '—' }}</td>
                                <td class="py-3 px-3 text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                    {{ optional($order->ordered_at)->format('d.m.Y H:i') ?: '—' }}
                                </td>
                                <td class="py-3 px-3 text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                    {{ optional($order->lastActivityAt())->format('d.m.Y H:i') ?: '—' }}
                                </td>
                                <td class="py-3 px-3 font-medium text-gray-900 dark:text-white whitespace-nowrap">
                                    {{ number_format((float) $order->total_amount, 2, ',', ' ') }} ₽
                                </td>
                                <td class="py-3 px-3">
                                    <span class="px-2 py-1 rounded-md text-xs font-medium {{ $order->statusBadgeClass() }}">
                                        {{ $order->statusLabel() }}
                                    </span>
                                    @if($order->statusDescription())
                                        <p class="text-xs text-gray-500 mt-1 max-w-[12rem]">{{ $order->statusDescription() }}</p>
                                    @endif
                                </td>
                                <td class="py-3 px-3 text-gray-600 dark:text-gray-300">
                                    {{ $order->responsibleContact?->full_name ?? '—' }}
                                </td>
                                <td class="py-3 px-5">
                                    <div class="flex items-center gap-1 min-h-[2rem] opacity-0 pointer-events-none transition-opacity group-hover:opacity-100 group-hover:pointer-events-auto group-focus-within:opacity-100 group-focus-within:pointer-events-auto">
                                        <a href="{{ route('distributor.orders.show', $order) }}"
                                           class="p-1.5 text-gray-400 hover:text-[#c3242a] rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
                                           title="Открыть">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            <span class="sr-only">Открыть</span>
                                        </a>
                                        @if($canEdit)
                                            <a href="{{ route('distributor.orders.show', $order) }}#edit"
                                               class="p-1.5 text-gray-400 hover:text-[#c3242a] rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
                                               title="Изменить">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                                <span class="sr-only">Изменить</span>
                                            </a>
                                        @endif
                                        <a href="{{ route('distributor.orders.print', $order) }}"
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
