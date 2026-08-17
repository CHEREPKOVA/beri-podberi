@extends('layouts.app')

@section('title', 'Закупка '.$order->order_number)
@section('heading')
    @include('orders._page_heading', ['order' => $order, 'titlePrefix' => 'Закупка'])
@endsection

@section('content')
@php
    use App\Services\Order\OrderStatusWorkflowService;
    $btnPrimary = 'inline-flex items-center justify-center h-10 px-4 rounded-lg text-sm font-medium border-2 border-[#c3242a] bg-[#c3242a] text-white hover:bg-[#a01e24] hover:border-[#a01e24] shadow-sm shadow-[#c3242a]/20';
    $btnOutline = 'inline-flex items-center justify-center h-10 px-4 rounded-lg text-sm font-medium border-2 border-[#c3242a] bg-transparent text-[#c3242a] hover:bg-red-50 dark:hover:bg-red-900/20';
    $btnGhost = 'inline-flex items-center justify-center h-10 px-4 rounded-lg text-sm border-2 border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700';
    $inputBrand = 'w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm focus:outline-none focus:border-[#c3242a] focus:ring-2 focus:ring-[#c3242a]/25';
@endphp
<div class="space-y-6" x-data="{ rejectOpen: false }">
    @if(session('success'))
        <div class="rounded-lg border border-[#c3242a]/30 bg-red-50 px-4 py-3 text-sm text-[#a01e24] dark:border-[#c3242a]/40 dark:bg-red-900/20 dark:text-red-100">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-lg border border-[#c3242a]/40 bg-red-50 px-4 py-3 text-sm text-[#a01e24]">
            <ul class="list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('distributor.purchases.index') }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-[#c3242a]">
            ← К списку покупок
        </a>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('distributor.purchases.print', $order) }}" target="_blank" class="{{ $btnGhost }}">Печать</a>
            <form method="POST" action="{{ route('distributor.purchases.reorder', $order) }}">
                @csrf
                <button type="submit" class="{{ $btnOutline }}" onclick="return confirm('Добавить позиции в корзину закупок?')">Повторить заказ</button>
            </form>
        </div>
    </div>

    @include('orders._status_timeline', ['order' => $order, 'progressIndex' => $progressIndex])

    <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm text-gray-500">Производитель</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ $order->manufacturerProfile?->displayName() ?? '—' }}</p>
                <p class="text-sm text-gray-500 mt-1">Создан {{ optional($order->ordered_at)->format('d.m.Y H:i') ?: '—' }} · {{ $order->sourceLabel() }}</p>
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-500">Сумма</p>
                <p class="text-2xl font-semibold text-[#c3242a]">{{ number_format((float) $order->total_amount, 2, ',', ' ') }} ₽</p>
            </div>
        </div>

        <form method="POST" action="{{ route('distributor.purchases.assign_responsible', $order) }}" class="mt-4 flex flex-wrap items-end gap-2">
            @csrf
            <div>
                <label class="block text-xs text-gray-500 mb-1">Ответственный</label>
                <div class="relative min-w-[14rem]">
                    <select name="responsible_contact_id" class="{{ $inputBrand }} appearance-none pr-10 cursor-pointer">
                        <option value="">Не назначен</option>
                        @foreach($managers as $manager)
                            <option value="{{ $manager->id }}" @selected((string) $order->responsible_contact_id === (string) $manager->id)>{{ $manager->full_name }}</option>
                        @endforeach
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </span>
                </div>
            </div>
            <button type="submit" class="{{ $btnGhost }}">Сохранить</button>
        </form>
    </section>

    @if($approvalProposal ?? null)
        <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border-2 border-[#c3242a]/30 p-5 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Предложенные изменения производителя</h2>
            @if($approvalProposal['supplier_comment'] ?? null)
                <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $approvalProposal['supplier_comment'] }}</p>
            @endif
            @if(!empty($approvalProposal['changes']))
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-gray-500 border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="text-left py-2 pr-3">Товар</th>
                                <th class="text-left py-2 pr-3">Кол-во</th>
                                <th class="text-left py-2 pr-3">Цена</th>
                                <th class="text-left py-2">Сумма</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($approvalProposal['changes'] as $change)
                                <tr class="border-b border-gray-100 dark:border-gray-700/60">
                                    <td class="py-3 pr-3">{{ $change['name'] ?? '—' }}</td>
                                    <td class="py-3 pr-3">
                                        @if(($change['old_quantity'] ?? null) !== ($change['new_quantity'] ?? null))
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
                                        {{ number_format((float) ($change['new_line_total'] ?? 0), 2, ',', ' ') }} ₽
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    @if(count($actions))
        <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Действия</h2>
            <div class="flex flex-wrap gap-2">
                @if(in_array(OrderStatusWorkflowService::ACTION_SUBMIT, $actions, true))
                    <form method="POST" action="{{ route('distributor.purchases.submit', $order) }}">
                        @csrf
                        <button type="submit" class="{{ $btnPrimary }}">Отправить производителю</button>
                    </form>
                @endif
                @if(in_array(OrderStatusWorkflowService::ACTION_CANCEL, $actions, true))
                    <form method="POST" action="{{ route('distributor.purchases.cancel', $order) }}" onsubmit="return confirm('Отменить заказ?')">
                        @csrf
                        <button type="submit" class="{{ $btnOutline }}">Отменить заказ</button>
                    </form>
                @endif
                @if(in_array(OrderStatusWorkflowService::ACTION_APPROVE_CHANGES, $actions, true))
                    <form method="POST" action="{{ route('distributor.purchases.approve_changes', $order) }}">
                        @csrf
                        <button type="submit" class="{{ $btnPrimary }}">Согласовать изменения</button>
                    </form>
                @endif
                @if(in_array(OrderStatusWorkflowService::ACTION_REJECT_CHANGES, $actions, true))
                    <button type="button" @click="rejectOpen = true" class="{{ $btnOutline }}">Отклонить изменения</button>
                @endif
                @if(in_array(OrderStatusWorkflowService::ACTION_CONFIRM_RECEIPT, $actions, true))
                    <form method="POST" action="{{ route('distributor.purchases.confirm_receipt', $order) }}" class="space-y-2 w-full sm:w-auto">
                        @csrf
                        <textarea name="completion_notes" rows="2" class="{{ $inputBrand }}" placeholder="Замечания при получении (необязательно)"></textarea>
                        <button type="submit" class="{{ $btnPrimary }}">Подтвердить получение</button>
                    </form>
                @endif
            </div>
            <div x-show="rejectOpen" x-cloak class="mt-4 border-2 border-[#c3242a]/30 rounded-lg p-4 bg-red-50/40 space-y-2">
                <form method="POST" action="{{ route('distributor.purchases.reject_changes', $order) }}">
                    @csrf
                    <label class="block text-sm text-gray-600 mb-1">Причина отклонения</label>
                    <textarea name="reason" rows="2" class="{{ $inputBrand }}"></textarea>
                    <div class="mt-2 flex gap-2">
                        <button type="submit" class="{{ $btnPrimary }}">Отклонить</button>
                        <button type="button" @click="rejectOpen = false" class="{{ $btnGhost }}">Отмена</button>
                    </div>
                </form>
            </div>
        </section>
    @endif

    @if($canEditItems)
        <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Изменить заказ</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-gray-500 border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th class="text-left py-2 px-2">Товар</th>
                            <th class="text-left py-2 px-2">Цена</th>
                            <th class="text-left py-2 px-2">Кол-во</th>
                            <th class="text-left py-2 px-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $item)
                            <tr class="border-b border-gray-100 dark:border-gray-700/60">
                                <td class="py-2 px-2">{{ $item->name }}</td>
                                <td class="py-2 px-2 whitespace-nowrap">{{ number_format((float) $item->unit_price, 2, ',', ' ') }} ₽</td>
                                <td class="py-2 px-2">
                                    <form method="POST" action="{{ route('distributor.purchases.items.update', [$order, $item]) }}" class="flex items-center gap-2">
                                        @csrf
                                        @method('PUT')
                                        <input type="number" name="quantity" value="{{ $item->quantity }}" min="0" class="w-20 rounded border border-gray-300 px-2 py-1">
                                        <button type="submit" class="text-xs text-[#c3242a]">OK</button>
                                    </form>
                                </td>
                                <td class="py-2 px-2">
                                    <form method="POST" action="{{ route('distributor.purchases.items.destroy', [$order, $item]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-gray-500 hover:text-[#c3242a]">Удалить</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($catalogProducts->isNotEmpty())
                <form method="POST" action="{{ route('distributor.purchases.items.store', $order) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Добавить товар</label>
                        <select name="product_id" class="{{ $inputBrand }} min-w-[16rem]" required>
                            <option value="">Выберите</option>
                            @foreach($catalogProducts as $product)
                                <option value="{{ $product->id }}">{{ $product->name }} · {{ number_format((float) $product->base_price, 2, ',', ' ') }} ₽</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Кол-во</label>
                        <input type="number" name="quantity" value="1" min="1" class="w-24 rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    </div>
                    <button type="submit" class="{{ $btnOutline }}">Добавить</button>
                </form>
            @endif
        </section>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
            @include('orders._items', ['order' => $order, 'show_product_links' => true])
            @include('orders._documents')
            @include('orders._history', ['hide_service' => true])
        </div>
        <div class="space-y-6">
            @include('orders._parties')
            @include('orders._finance')
            @include('orders._logistics')
        </div>
    </div>
</div>
@endsection
