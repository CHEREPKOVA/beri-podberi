@extends('layouts.app')

@section('title', 'Мои заказы')
@section('heading', 'Мои заказы')

@section('content')
@php
    use App\Models\PlatformOrder;
@endphp
<div class="space-y-6">
    @if(session('success'))
        <div class="rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-900 dark:border-green-700 dark:bg-green-900/20 dark:text-green-100">
            {{ session('success') }}
        </div>
    @endif

    @if($orders->isEmpty())
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
            <p class="text-gray-600 dark:text-gray-300 mb-4">Заказов пока нет</p>
            <a href="{{ route('buyer.catalog.index') }}"
               class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-[#c3242a] text-white hover:bg-[#a01e24]">
                Перейти в каталог
            </a>
        </div>
    @else
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th class="text-left py-3 px-5">Номер / дата</th>
                            <th class="text-left py-3 px-3">Дистрибьютор</th>
                            <th class="text-left py-3 px-3">Позиции</th>
                            <th class="text-left py-3 px-3">Сумма</th>
                            <th class="text-left py-3 px-3">Статус</th>
                            <th class="text-left py-3 px-5">Обновлён</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                            @php
                                $needsAttention = $order->requiresBuyerAttention();
                                $rowClass = $needsAttention
                                    ? 'border-b border-[#c3242a]/20 bg-red-50/60 dark:bg-red-900/15'
                                    : 'border-b border-gray-100 dark:border-gray-700/60 hover:bg-gray-50 dark:hover:bg-gray-900/30';
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <td class="py-3 px-5">
                                    <a href="{{ route('buyer.orders.show', $order) }}" class="font-medium text-[#c3242a] hover:underline">
                                        {{ $order->order_number }}
                                    </a>
                                    <p class="text-xs text-gray-500 mt-0.5">{{ optional($order->ordered_at)->format('d.m.Y H:i') ?: '—' }}</p>
                                    @if($order->status === PlatformOrder::STATUS_NEEDS_APPROVAL)
                                        <p class="text-xs font-medium text-[#c3242a] mt-1">Требуется согласование изменений</p>
                                    @elseif($order->status === PlatformOrder::STATUS_SHIPPED)
                                        <p class="text-xs font-medium text-[#c3242a] mt-1">Подтвердите получение</p>
                                    @endif
                                </td>
                                <td class="py-3 px-3 text-gray-900 dark:text-white">{{ $order->distributorProfile?->displayName() ?? '—' }}</td>
                                <td class="py-3 px-3 text-gray-900 dark:text-white">{{ $order->items_count }}</td>
                                <td class="py-3 px-3 font-medium text-gray-900 dark:text-white whitespace-nowrap">
                                    {{ number_format((float) $order->total_amount, 2, ',', ' ') }} ₽
                                </td>
                                <td class="py-3 px-3">
                                    <span class="px-2 py-1 rounded-md text-xs font-medium {{ $order->statusBadgeClass() }}">
                                        {{ $order->statusLabel() }}
                                    </span>
                                    @if($order->statusDescription())
                                        <p class="text-xs text-gray-500 mt-1 max-w-[14rem]">{{ $order->statusDescription() }}</p>
                                    @endif
                                </td>
                                <td class="py-3 px-5 text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                    {{ optional($order->lastActivityAt())->format('d.m.Y H:i') ?: '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div>{{ $orders->links() }}</div>
    @endif
</div>
@endsection
