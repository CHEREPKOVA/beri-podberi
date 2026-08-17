@extends('layouts.app')

@section('title', 'Заказ '.$order->order_number)
@section('heading')
    @include('orders._page_heading', ['order' => $order])
@endsection

@section('content')
@php
    use App\Services\Order\OrderStatusWorkflowService;
    $btnPrimary = 'inline-flex items-center justify-center h-10 px-4 rounded-lg text-sm font-medium border-2 border-[#c3242a] bg-[#c3242a] text-white hover:bg-[#a01e24] hover:border-[#a01e24] shadow-sm shadow-[#c3242a]/20';
    $btnOutline = 'inline-flex items-center justify-center h-10 px-4 rounded-lg text-sm font-medium border-2 border-[#c3242a] bg-transparent text-[#c3242a] hover:bg-red-50 dark:hover:bg-red-900/20';
    $btnGhost = 'inline-flex items-center justify-center h-10 px-4 rounded-lg text-sm border-2 border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700';
    $inputBrand = 'w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm focus:outline-none focus:border-[#c3242a] focus:ring-2 focus:ring-[#c3242a]/25';
@endphp
<div class="space-y-6" x-data="{ rejectOpen: false, cancelOpen: false }">
    @if(session('success'))
        <div class="rounded-lg border border-[#c3242a]/30 bg-red-50 px-4 py-3 text-sm text-[#a01e24] dark:border-[#c3242a]/40 dark:bg-red-900/20 dark:text-red-100">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-lg border border-[#c3242a]/40 bg-red-50 px-4 py-3 text-sm text-[#a01e24] dark:border-[#c3242a]/50 dark:bg-red-900/20 dark:text-red-100">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div>
        <a href="{{ route('buyer.orders.index') }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-[#c3242a]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            К списку заказов
        </a>
    </div>

    @include('orders._status_timeline', ['order' => $order, 'progressIndex' => $progressIndex])

    @if($approvalProposal ?? null)
        <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border-2 border-[#c3242a]/30 p-5 space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Предложенные изменения</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Поставщик изменил заказ
                        @if($approvalProposal['created_at'] ?? null)
                            · {{ $approvalProposal['created_at']->format('d.m.Y H:i') }}
                        @endif
                    </p>
                </div>
                @if(($approvalProposal['previous_total'] ?? null) !== null && ($approvalProposal['new_total'] ?? null) !== null)
                    <div class="text-sm text-right">
                        <p class="text-gray-500">Сумма заказа</p>
                        <p>
                            <span class="line-through text-gray-400">{{ number_format((float) $approvalProposal['previous_total'], 2, ',', ' ') }} ₽</span>
                            <span class="ml-2 font-semibold text-[#c3242a]">{{ number_format((float) $approvalProposal['new_total'], 2, ',', ' ') }} ₽</span>
                        </p>
                    </div>
                @endif
            </div>

            @if($approvalProposal['supplier_comment'] ?? null)
                <div class="rounded-lg bg-red-50/70 dark:bg-red-900/10 border border-[#c3242a]/20 px-4 py-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-[#c3242a] mb-1">Комментарий поставщика</p>
                    <p class="text-sm text-gray-900 dark:text-white whitespace-pre-line">{{ $approvalProposal['supplier_comment'] }}</p>
                </div>
            @endif

            @if(!empty($approvalProposal['changes']))
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="text-left py-2 pr-3">Товар</th>
                                <th class="text-left py-2 pr-3">Кол-во</th>
                                <th class="text-left py-2 pr-3">Цена</th>
                                <th class="text-left py-2">Сумма</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($approvalProposal['changes'] as $change)
                                <tr class="border-b border-gray-100 dark:border-gray-700/60 align-top {{ (($change['old_quantity'] ?? null) != ($change['new_quantity'] ?? null) || abs((float) ($change['old_unit_price'] ?? 0) - (float) ($change['new_unit_price'] ?? 0)) > 0.009) ? 'bg-red-50/40 dark:bg-red-900/10' : '' }}">
                                    <td class="py-3 pr-3">
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $change['name'] ?? 'Товар' }}</p>
                                        <p class="text-xs text-gray-500">{{ $change['sku'] ?: '—' }}</p>
                                        @if(!empty($change['reason']))
                                            <p class="text-xs text-[#c3242a] mt-1">Причина: {{ $change['reason'] }}</p>
                                        @endif
                                    </td>
                                    <td class="py-3 pr-3 whitespace-nowrap">
                                        @if((int) ($change['old_quantity'] ?? 0) !== (int) ($change['new_quantity'] ?? 0))
                                            <span class="line-through text-gray-400">{{ $change['old_quantity'] }}</span>
                                            <span class="ml-1 font-medium text-[#c3242a]">{{ $change['new_quantity'] }}</span>
                                        @else
                                            {{ $change['new_quantity'] }}
                                        @endif
                                    </td>
                                    <td class="py-3 pr-3 whitespace-nowrap">
                                        @if(abs((float) ($change['old_unit_price'] ?? 0) - (float) ($change['new_unit_price'] ?? 0)) > 0.009)
                                            <span class="line-through text-gray-400">{{ number_format((float) $change['old_unit_price'], 2, ',', ' ') }} ₽</span>
                                            <span class="ml-1 font-medium text-[#c3242a]">{{ number_format((float) $change['new_unit_price'], 2, ',', ' ') }} ₽</span>
                                        @else
                                            {{ number_format((float) $change['new_unit_price'], 2, ',', ' ') }} ₽
                                        @endif
                                    </td>
                                    <td class="py-3 whitespace-nowrap">
                                        @if(abs((float) ($change['old_line_total'] ?? 0) - (float) ($change['new_line_total'] ?? 0)) > 0.009)
                                            <span class="line-through text-gray-400">{{ number_format((float) $change['old_line_total'], 2, ',', ' ') }} ₽</span>
                                            <span class="ml-1 font-medium text-[#c3242a]">{{ number_format((float) $change['new_line_total'], 2, ',', ' ') }} ₽</span>
                                        @else
                                            {{ number_format((float) $change['new_line_total'], 2, ',', ' ') }} ₽
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Детализация изменений по позициям недоступна для этого предложения.
                    Актуальный состав заказа смотрите в блоке ниже.
                </p>
            @endif
        </section>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
            @include('orders._items', ['order' => $order, 'show_product_links' => $show_product_links ?? true])
            @include('orders._documents')
            @include('orders._history', ['hide_service' => true, 'action_labels' => $action_labels ?? null])
        </div>

        <div class="space-y-6">
            @if(count($actions))
                <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Действия</h2>
                    <div class="flex flex-col gap-2">
                        @if(in_array(OrderStatusWorkflowService::ACTION_CANCEL, $actions, true))
                            <button type="button" @click="cancelOpen = true" class="{{ $btnOutline }} w-full">Отменить заказ</button>
                        @endif

                        @if(in_array(OrderStatusWorkflowService::ACTION_APPROVE_CHANGES, $actions, true))
                            <form method="POST" action="{{ route('buyer.orders.approve_changes', $order) }}">
                                @csrf
                                <button type="submit" class="{{ $btnPrimary }} w-full">Согласовать изменения</button>
                            </form>
                        @endif

                        @if(in_array(OrderStatusWorkflowService::ACTION_REJECT_CHANGES, $actions, true))
                            <button type="button" @click="rejectOpen = true" class="{{ $btnOutline }} w-full">Отклонить изменения</button>
                        @endif

                        @if(in_array(OrderStatusWorkflowService::ACTION_CONFIRM_RECEIPT, $actions, true))
                            <form method="POST" action="{{ route('buyer.orders.confirm_receipt', $order) }}" class="space-y-2">
                                @csrf
                                <textarea name="completion_notes" rows="2" class="{{ $inputBrand }}" placeholder="Замечания при получении (необязательно)"></textarea>
                                <button type="submit" class="{{ $btnPrimary }} w-full">Подтвердить получение</button>
                            </form>
                        @endif
                    </div>

                    <div x-show="rejectOpen" x-cloak class="mt-4 border-2 border-[#c3242a]/30 rounded-lg p-4 bg-red-50/40 dark:bg-red-900/10 space-y-2">
                        <form method="POST" action="{{ route('buyer.orders.reject_changes', $order) }}">
                            @csrf
                            <label class="block text-sm text-gray-600 dark:text-gray-300 mb-1">Причина отклонения</label>
                            <textarea name="reason" rows="2" class="{{ $inputBrand }}" placeholder="Не согласен с изменениями..."></textarea>
                            <div class="mt-2 flex flex-col gap-2">
                                <button type="submit" class="{{ $btnPrimary }} w-full">Отклонить</button>
                                <button type="button" @click="rejectOpen = false" class="{{ $btnGhost }} w-full">Отмена</button>
                            </div>
                        </form>
                    </div>
                </section>
            @endif

            @include('orders._parties')
            @include('orders._finance')
            @include('orders._logistics')
        </div>
    </div>

    @if(in_array(OrderStatusWorkflowService::ACTION_CANCEL, $actions, true))
        <div
            x-show="cancelOpen"
            x-cloak
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @keydown.escape.window="cancelOpen = false"
            @click.self="cancelOpen = false"
            role="dialog"
            aria-modal="true"
            aria-labelledby="cancel-order-title"
        >
            <div
                class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-gray-800"
                x-transition
                @click.stop
            >
                <h3 id="cancel-order-title" class="text-lg font-semibold text-gray-900 dark:text-white">
                    Отменить заказ?
                </h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Заказ {{ $order->order_number }} будет отменён. Это действие нельзя отменить.
                </p>
                <form method="POST" action="{{ route('buyer.orders.cancel', $order) }}" class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    @csrf
                    <button type="button" @click="cancelOpen = false" class="{{ $btnGhost }} w-full sm:w-auto">
                        Назад
                    </button>
                    <button type="submit" class="{{ $btnPrimary }} w-full sm:w-auto">
                        Да, отменить
                    </button>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
