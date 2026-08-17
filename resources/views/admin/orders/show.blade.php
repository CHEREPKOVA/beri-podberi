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
    $actionLabels = $action_labels ?? OrderStatusWorkflowService::actionLabels();
@endphp
<div class="space-y-6" x-data="{ pauseOpen: false }">
    @if(session('success') && session('success') !== 'Заказ приостановлен.')
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

    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.orders.index') }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-[#c3242a]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            К списку заказов
        </a>
        @php
            $visibleProblemFlags = array_values(array_filter(
                $problemFlags,
                fn ($flag) => $flag !== 'paused'
            ));
        @endphp
        @if($visibleProblemFlags !== [])
            <div class="flex flex-wrap gap-1.5">
                @foreach($visibleProblemFlags as $flag)
                    <span class="inline-flex px-2 py-1 rounded-md text-xs font-medium bg-[#c3242a]/10 text-[#a01e24] ring-1 ring-[#c3242a]/25">
                        {{ $order->problemFlagLabel($flag) }}
                    </span>
                @endforeach
            </div>
        @endif
    </div>

    @include('orders._status_timeline', [
        'order' => $order,
        'progressIndex' => $progressIndex,
    ])

    <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 space-y-5">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Модерация</h2>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
            @if(in_array(OrderStatusWorkflowService::ACTION_ADMIN_STATUS_CHANGE, $actions, true))
                <form method="POST" action="{{ route('admin.orders.update_status', $order) }}" class="space-y-3 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    @csrf
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Изменить статус</h3>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Новый статус</label>
                        <div class="relative">
                            <select name="status" class="{{ $inputBrand }} appearance-none pr-9 cursor-pointer" required>
                                @foreach($statusLabels as $slug => $label)
                                    <option value="{{ $slug }}" @selected(old('status', $order->status) === $slug)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <svg class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Служебный комментарий</label>
                        <textarea name="comment" rows="3" class="{{ $inputBrand }}" required placeholder="Причина вмешательства...">{{ old('comment') }}</textarea>
                    </div>
                    <button type="submit" class="{{ $btnPrimary }}">Сохранить статус</button>
                </form>
            @endif

            <form method="POST" action="{{ route('admin.orders.comment', $order) }}" class="space-y-3 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                @csrf
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Служебный комментарий</h3>
                <textarea name="comment" rows="3" class="{{ $inputBrand }}" required placeholder="Внутренняя отметка или сообщение сторонам...">{{ old('comment') }}</textarea>
                <label class="flex items-center gap-2.5 text-sm text-gray-700 dark:text-gray-300 cursor-pointer select-none">
                    <input type="checkbox" name="notify_parties" value="1" class="sr-only peer" @checked(old('notify_parties'))>
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md border-[1.25px] border-gray-300 bg-white transition-colors pointer-events-none peer-checked:border-[#c3242a] peer-checked:bg-[#c3242a] peer-checked:[&>svg]:opacity-100 peer-focus:ring-2 peer-focus:ring-[#c3242a]/30 dark:border-gray-600 dark:bg-gray-800 dark:peer-checked:border-[#c3242a] dark:peer-checked:bg-[#c3242a]">
                        <svg class="h-3.5 w-3.5 text-white opacity-0 transition-opacity" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                            <path d="M11.6666 3.5L5.24992 9.91667L2.33325 7" stroke="currentColor" stroke-width="1.94437" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    Уведомить покупателя и поставщика
                </label>
                <button type="submit" class="{{ $btnOutline }}">Добавить</button>
            </form>

            <div class="space-y-3 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Приостановка</h3>
                @if(in_array(OrderStatusWorkflowService::ACTION_PAUSE, $actions, true))
                    <button type="button" @click="pauseOpen = !pauseOpen" class="{{ $btnOutline }}">Приостановить заказ</button>
                    <div x-show="pauseOpen" x-cloak class="space-y-2">
                        <form method="POST" action="{{ route('admin.orders.pause', $order) }}">
                            @csrf
                            <textarea name="pause_reason" rows="3" class="{{ $inputBrand }}" required placeholder="Причина приостановки...">{{ old('pause_reason') }}</textarea>
                            <div class="mt-2 flex gap-2">
                                <button type="submit" class="{{ $btnPrimary }}">Приостановить</button>
                                <button type="button" @click="pauseOpen = false" class="{{ $btnGhost }}">Отмена</button>
                            </div>
                        </form>
                    </div>
                @elseif(in_array(OrderStatusWorkflowService::ACTION_RESUME, $actions, true))
                    <form method="POST" action="{{ route('admin.orders.resume', $order) }}" class="space-y-2">
                        @csrf
                        <textarea name="comment" rows="2" class="{{ $inputBrand }}" placeholder="Комментарий (необязательно)">{{ old('comment') }}</textarea>
                        <button type="submit" class="{{ $btnPrimary }}">Снять приостановку</button>
                    </form>
                @else
                    <p class="text-sm text-gray-500">Для текущего статуса приостановка недоступна.</p>
                @endif
            </div>
        </div>
    </section>

    @if($approvalProposal ?? null)
        <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border-2 border-[#c3242a]/30 p-5 space-y-3">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Предложенные изменения поставщика</h2>
            @if($approvalProposal['supplier_comment'] ?? null)
                <p class="text-sm text-gray-700 dark:text-gray-200 whitespace-pre-line">{{ $approvalProposal['supplier_comment'] }}</p>
            @endif
            @if(($approvalProposal['previous_total'] ?? null) !== null && ($approvalProposal['new_total'] ?? null) !== null)
                <p class="text-sm">
                    <span class="line-through text-gray-400">{{ number_format((float) $approvalProposal['previous_total'], 2, ',', ' ') }} ₽</span>
                    <span class="ml-2 font-semibold text-[#c3242a]">{{ number_format((float) $approvalProposal['new_total'], 2, ',', ' ') }} ₽</span>
                </p>
            @endif
        </section>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
            @include('orders._items', ['order' => $order, 'show_product_links' => false])
            @include('orders._documents')
            @include('orders._history', [
                'hide_service' => false,
                'title' => 'Полная история действий',
                'action_labels' => $actionLabels,
            ])
        </div>

        <div class="space-y-6">
            @include('orders._parties')
            @include('orders._finance')
            @include('orders._logistics')
        </div>
    </div>
</div>
@endsection
