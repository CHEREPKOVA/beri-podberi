@extends('layouts.app')

@section('title', 'Оформление заказа')
@section('heading', 'Оформление заказа')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
@endpush

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('buyer.cart.index') }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-[#c3242a]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Назад в корзину
        </a>
        <p class="text-sm text-gray-500 text-right">
            Поставщик: <span class="font-medium text-gray-900 dark:text-white">{{ $distributor->displayName() }}</span>
            @if(!empty($group['region_name']))
                <span class="block text-xs mt-0.5">Регион: {{ $group['region_name'] }}</span>
            @endif
            @if(!empty($group['warehouse_name']))
                <span class="block text-xs">Склад отгрузки: {{ $group['warehouse_name'] }}</span>
            @endif
            @if(!empty($group['price_scope_label']))
                <span class="block text-xs">{{ $group['price_scope_label'] }}</span>
            @endif
        </p>
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

    <form method="POST" action="{{ route('buyer.checkout.store', $distributor) }}" class="space-y-6" id="checkout-form">
        @csrf
        @include('buyer.checkout._section', [
            'namePrefix' => '',
            'dotPrefix' => '',
            'group' => $group,
            'distributor' => $distributor,
            'deliveryMethods' => $deliveryMethods,
            'transportCompanies' => $transportCompanies,
            'deliveryAddresses' => $deliveryAddresses,
            'defaultAddressId' => $defaultAddressId,
            'deliveryNotes' => $deliveryNotes,
            'priceChangeNotices' => $priceChangeNotices,
            'itemWarnings' => $itemWarnings,
        ])

        <div class="max-w-sm xl:ml-auto">
            <button type="submit"
                    class="w-full inline-flex justify-center items-center px-4 py-3 rounded-lg text-sm font-medium bg-[#c3242a] text-white hover:bg-[#a01e24] shadow-md shadow-[#c3242a]/20">
                Отправить заказ
            </button>
            <a href="{{ route('buyer.cart.index') }}" class="mt-3 block text-center text-sm text-gray-500 hover:text-[#c3242a]">
                Вернуться и изменить корзину
            </a>
        </div>
    </form>
</div>
@endsection

@include('buyer.checkout._flatpickr')
