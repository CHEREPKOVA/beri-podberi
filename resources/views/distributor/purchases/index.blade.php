@extends('layouts.app')

@section('title', 'Мои покупки')
@section('heading', 'Мои покупки')

@section('content')
@php
    use App\Models\PlatformOrder;
    $inputClass = 'w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm';
@endphp
<div class="space-y-6">
    @if(session('success'))
        <div class="rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-900 dark:border-green-700 dark:bg-green-900/20 dark:text-green-100">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('distributor.purchases.cart.index') }}"
           class="inline-flex items-center h-9 px-3 rounded-lg text-sm border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
            Корзина закупок@if(($purchaseCartItemsCount ?? 0) > 0) ({{ $purchaseCartItemsCount }})@endif
        </a>
        <a href="{{ route('buyer.catalog.index') }}"
           class="inline-flex items-center h-9 px-3 rounded-lg text-sm bg-[#c3242a] text-white hover:bg-[#a01e24]">
            Каталог производителей
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="p-5 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">Фильтры</h2>
            <form method="GET" action="{{ route('distributor.purchases.index') }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Номер</label>
                    <input type="text" name="number" value="{{ request('number') }}" placeholder="PO-..." class="{{ $inputClass }} w-40">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Производитель</label>
                    <input type="text" name="manufacturer" value="{{ request('manufacturer') }}" placeholder="Название / ИНН" class="{{ $inputClass }} w-48">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Статус</label>
                    <select name="status" class="{{ $inputClass }} appearance-none min-w-[11rem] pr-8">
                        <option value="">Все статусы</option>
                        @foreach($statusLabels as $slug => $label)
                            <option value="{{ $slug }}" @selected(request('status') === $slug)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Ответственный</label>
                    <select name="responsible_contact_id" class="{{ $inputClass }} appearance-none min-w-[11rem] pr-8">
                        <option value="">Все</option>
                        @foreach($managers as $manager)
                            <option value="{{ $manager->id }}" @selected((string) request('responsible_contact_id') === (string) $manager->id)>{{ $manager->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">С</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">По</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Сумма от</label>
                    <input type="number" step="0.01" name="amount_from" value="{{ request('amount_from') }}" class="{{ $inputClass }} w-28">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Сумма до</label>
                    <input type="number" step="0.01" name="amount_to" value="{{ request('amount_to') }}" class="{{ $inputClass }} w-28">
                </div>
                <label class="inline-flex items-center gap-2 h-10 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" name="attention" value="1" @checked(request()->boolean('attention'))
                           class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
                    Требуют внимания
                </label>
                <button type="submit" class="h-10 px-4 rounded-lg text-sm font-medium border border-[#c3242a] text-[#c3242a] hover:bg-red-50">Применить</button>
                @if(request()->hasAny(['number', 'manufacturer', 'status', 'responsible_contact_id', 'date_from', 'date_to', 'amount_from', 'amount_to', 'attention']))
                    <a href="{{ route('distributor.purchases.index') }}" class="text-sm text-gray-500 py-2">Сбросить</a>
                @endif
            </form>
        </div>

        @if($orders->isEmpty())
            <div class="p-8 text-center">
                <p class="text-gray-600 dark:text-gray-300 mb-4">Закупок пока нет</p>
                <a href="{{ route('buyer.catalog.index') }}" class="text-sm text-[#c3242a] hover:underline">Открыть каталог</a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th class="text-left py-3 px-5">Номер</th>
                            <th class="text-left py-3 px-3">Производитель</th>
                            <th class="text-left py-3 px-3">Создан</th>
                            <th class="text-left py-3 px-3">Сумма</th>
                            <th class="text-left py-3 px-3">Статус</th>
                            <th class="text-left py-3 px-5">Ответственный</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                            @php
                                $needsAttention = $order->requiresBuyerAttention();
                                $rowClass = $needsAttention
                                    ? 'group border-b border-[#c3242a]/20 bg-red-50/60 dark:bg-red-900/15'
                                    : 'group border-b border-gray-100 dark:border-gray-700/60 hover:bg-gray-50 dark:hover:bg-gray-900/30';
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <td class="py-3 px-5">
                                    <a href="{{ route('distributor.purchases.show', $order) }}" class="font-medium text-[#c3242a] hover:underline">{{ $order->order_number }}</a>
                                    @if($order->status === PlatformOrder::STATUS_NEEDS_APPROVAL)
                                        <p class="text-xs font-medium text-[#c3242a] mt-1">Изменено производителем</p>
                                    @endif
                                    <div class="mt-2 hidden group-hover:flex gap-2">
                                        <a href="{{ route('distributor.purchases.show', $order) }}" class="text-xs text-[#c3242a] hover:underline">Открыть заказ</a>
                                        <a href="{{ route('distributor.purchases.print', $order) }}" target="_blank" class="text-xs text-gray-500 hover:underline">Печать</a>
                                    </div>
                                </td>
                                <td class="py-3 px-3">
                                    <div class="flex items-center gap-2">
                                        @if($order->manufacturerProfile?->logo_url)
                                            <img src="{{ $order->manufacturerProfile->logo_url }}" alt="" class="h-7 w-7 rounded object-contain bg-white">
                                        @endif
                                        <span class="text-gray-900 dark:text-white">{{ $order->manufacturerProfile?->displayName() ?? '—' }}</span>
                                    </div>
                                </td>
                                <td class="py-3 px-3 text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ optional($order->ordered_at)->format('d.m.Y H:i') ?: '—' }}</td>
                                <td class="py-3 px-3 font-medium whitespace-nowrap">{{ number_format((float) $order->total_amount, 2, ',', ' ') }} ₽</td>
                                <td class="py-3 px-3">
                                    <span class="px-2 py-1 rounded-md text-xs font-medium {{ $order->statusBadgeClass() }}">{{ $order->statusLabel() }}</span>
                                </td>
                                <td class="py-3 px-5 text-gray-600 dark:text-gray-300">{{ $order->responsibleContact?->full_name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $orders->links() }}</div>
        @endif
    </div>
</div>
@endsection
