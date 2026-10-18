@extends('layouts.app')

@section('title', 'Оформление заказов')
@section('heading', 'Оформление заказов')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
@endpush

@section('content')
@php
    $ordersCount = $sections->count();
    $grandTotal = $sections->sum(fn (array $section): float => (float) $section['group']['subtotal']);
@endphp
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('buyer.cart.index') }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-[#c3242a]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Назад в корзину
        </a>
        <p class="text-sm text-gray-500">Будет создано заказов: <span class="font-medium text-gray-900 dark:text-white">{{ $ordersCount }}</span></p>
    </div>

    @if($errors->any())
        <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-700 dark:bg-red-900/20 dark:text-red-100">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($blockedItems->isNotEmpty())
        <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-100">
            {{ $blockedItems->count() }} поз. останутся в корзине — их нельзя оформить, пока не будут исправлены ошибки.
        </div>
    @endif

    <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Предварительный список заказов</h2>
        <ul class="space-y-2">
            @foreach($sections as $section)
                @php $group = $section['group']; @endphp
                <li class="flex flex-wrap items-center justify-between gap-2 text-sm border-b border-gray-100 dark:border-gray-700 pb-2 last:border-0 last:pb-0">
                    <span class="text-gray-700 dark:text-gray-300">
                        {{ $group['sidebar_label'] ?? $group['distributor_name'] }}
                        · {{ $group['items_count'] }} поз.
                        @if(!empty($group['delivery_method_names']))
                            · {{ implode(', ', $group['delivery_method_names']) }}
                        @endif
                    </span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ number_format($group['subtotal'], 2, ',', ' ') }} ₽</span>
                </li>
            @endforeach
        </ul>
        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <span class="text-sm text-gray-500">Итого по всем заказам</span>
            <span class="text-xl font-semibold text-[#c3242a]">{{ number_format($grandTotal, 2, ',', ' ') }} ₽</span>
        </div>
    </section>

    <form method="POST" action="{{ route('buyer.checkout.batch.store') }}" class="space-y-8" id="checkout-form">
        @csrf

        @foreach($sections as $index => $section)
            <section class="rounded-xl border border-gray-200 dark:border-gray-700 p-5 space-y-4 bg-gray-50/60 dark:bg-gray-900/20">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Заказ {{ $index + 1 }} из {{ $ordersCount }} · {{ $section['distributor']->displayName() }}
                    </h2>
                    <p class="text-sm text-gray-500">
                        @if(!empty($section['group']['region_name']))
                            Регион: {{ $section['group']['region_name'] }}
                        @endif
                        @if(!empty($section['group']['warehouse_name']))
                            · Склад отгрузки: {{ $section['group']['warehouse_name'] }}
                        @else
                            · Склад отгрузки: под заказ
                        @endif
                        @if(!empty($section['group']['price_scope_label']))
                            · {{ $section['group']['price_scope_label'] }}
                        @endif
                    </p>
                </div>

                @include('buyer.checkout._section', [
                    'namePrefix' => 'orders['.$index.']',
                    'dotPrefix' => 'orders.'.$index,
                    'group' => $section['group'],
                    'distributor' => $section['distributor'],
                    'deliveryMethods' => $section['delivery_methods'],
                    'transportCompanies' => $section['transport_companies'],
                    'deliveryAddresses' => $section['delivery_addresses'],
                    'defaultAddressId' => $section['default_address_id'],
                    'deliveryNotes' => $section['delivery_notes'],
                    'priceChangeNotices' => $section['price_change_notices'],
                    'itemWarnings' => $section['item_warnings'],
                ])
            </section>
        @endforeach

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3">
            <a href="{{ route('buyer.cart.index') }}" class="text-center text-sm text-gray-500 hover:text-[#c3242a]">
                Вернуться и изменить корзину
            </a>
            <button type="submit"
                    class="inline-flex justify-center items-center px-5 py-3 rounded-lg text-sm font-medium bg-[#c3242a] text-white hover:bg-[#a01e24] shadow-md shadow-[#c3242a]/20">
                Создать заказы ({{ $ordersCount }})
            </button>
        </div>
    </form>
</div>
@endsection

@include('buyer.checkout._flatpickr')
