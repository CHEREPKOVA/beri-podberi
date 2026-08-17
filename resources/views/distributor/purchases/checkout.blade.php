@extends('layouts.app')

@section('title', 'Оформление закупки')
@section('heading', 'Оформление закупки')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
@endpush

@section('content')
@php
    $input = 'form-control-brand';
    $select = $input.' appearance-none pr-10 cursor-pointer';
@endphp
<div class="space-y-6" x-data="{
    deliveryMethodId: @js(old('delivery_method_id', $deliveryMethods->first()?->id)),
    transportSlug: 'transport_company',
    methods: @js($deliveryMethods->map(fn ($m) => ['id' => $m->id, 'slug' => $m->slug])->values()),
    currentSlug() {
        return this.methods.find(m => Number(m.id) === Number(this.deliveryMethodId))?.slug || null;
    }
}">
    <a href="{{ route('distributor.purchases.cart.index') }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-[#c3242a]">
        ← Назад в корзину
    </a>

    @if($errors->any())
        <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900">
            <ul class="list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $manufacturer->displayName() }}</h2>
        <p class="text-sm text-gray-500 mt-1">{{ $group['items_count'] }} поз. · {{ number_format($group['subtotal'], 2, ',', ' ') }} ₽</p>
        @if($deliveryNotes)
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-300 whitespace-pre-line">{{ $deliveryNotes }}</p>
        @endif
    </div>

    <form method="POST" action="{{ route('distributor.purchases.checkout.store', $manufacturer->id) }}" class="space-y-6">
        @csrf
        <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Параметры поставки</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Способ доставки</label>
                    <div class="relative">
                        <select name="delivery_method_id" x-model="deliveryMethodId" class="{{ $select }}" required>
                            @foreach($deliveryMethods as $method)
                                <option value="{{ $method->id }}" @selected((string) old('delivery_method_id', $deliveryMethods->first()?->id) === (string) $method->id)>
                                    {{ $method->name }}
                                </option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </span>
                    </div>
                </div>
                <div x-show="currentSlug() === transportSlug" x-cloak>
                    <label class="block text-xs text-gray-500 mb-1">Транспортная компания</label>
                    <div class="relative">
                        <select name="transport_company_id" class="{{ $select }}">
                            <option value="">Выберите</option>
                            @foreach($transportCompanies as $company)
                                <option value="{{ $company->id }}" @selected((string) old('transport_company_id') === (string) $company->id)>{{ $company->name }}</option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </span>
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Склад поставки</label>
                    <div class="relative">
                        <select name="distributor_warehouse_id" class="{{ $select }}">
                            <option value="">Автоматически</option>
                            @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected((string) old('distributor_warehouse_id') === (string) $warehouse->id)>
                                    {{ $warehouse->name }}@if($warehouse->region) · {{ $warehouse->region->name }}@endif
                                </option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </span>
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Желаемая дата</label>
                    <div class="relative">
                        <input type="text"
                               name="delivery_date"
                               value="{{ old('delivery_date') }}"
                               placeholder="Выберите дату"
                               autocomplete="off"
                               class="js-flatpickr {{ $input }} pr-10 cursor-pointer">
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </span>
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Ответственный</label>
                    <div class="relative">
                        <select name="responsible_contact_id" class="{{ $select }}">
                            <option value="">Не назначен</option>
                            @foreach($managers as $manager)
                                <option value="{{ $manager->id }}" @selected((string) old('responsible_contact_id') === (string) $manager->id)>{{ $manager->full_name }}</option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </span>
                    </div>
                </div>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Комментарий</label>
                <textarea name="buyer_comment" rows="3" class="{{ $input }}">{{ old('buyer_comment') }}</textarea>
            </div>
        </section>

        <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Состав</h2>
            </div>
            <table class="min-w-full text-sm">
                <thead class="text-gray-500 border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="text-left py-3 px-5">Товар</th>
                        <th class="text-left py-3 px-3">Цена</th>
                        <th class="text-left py-3 px-3">Кол-во</th>
                        <th class="text-left py-3 px-5">Сумма</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($group['items'] as $item)
                        <tr class="border-b border-gray-100 dark:border-gray-700/60">
                            <td class="py-3 px-5">{{ $item['name'] }} <span class="text-xs text-gray-500">{{ $item['sku'] }}</span></td>
                            <td class="py-3 px-3 whitespace-nowrap">{{ number_format($item['unit_price'], 2, ',', ' ') }} ₽</td>
                            <td class="py-3 px-3">{{ $item['quantity'] }}</td>
                            <td class="py-3 px-5 font-medium whitespace-nowrap">{{ number_format($item['line_total'], 2, ',', ' ') }} ₽</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <button type="submit" class="inline-flex items-center h-11 px-5 rounded-lg text-sm font-medium bg-[#c3242a] text-white hover:bg-[#a01e24]">
            Создать заказ
        </button>
    </form>
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
            minDate: 'today',
            allowInput: false,
            disableMobile: true,
            altInputClass: el.className.replace(/\bjs-flatpickr\b/g, '').trim(),
        });
    });
});
</script>
@endpush
