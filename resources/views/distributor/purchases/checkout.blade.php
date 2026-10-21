@extends('layouts.app')

@section('title', 'Оформление закупки')
@section('heading', 'Оформление закупки')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
@endpush

@section('content')
@php
    $input = 'form-control-brand';
    $warehouseDetails = $warehouses->mapWithKeys(fn ($w) => [
        (string) $w->id => [
            'address' => $w->address ?: null,
            'phone' => $w->phone ?: null,
            'region' => $w->region?->name,
        ],
    ]);
    $initialWarehouseId = old('distributor_warehouse_id', $defaultWarehouseId);
@endphp
<div class="space-y-6" x-data="{
    deliveryMethodId: @js(old('delivery_method_id', $deliveryMethods->first()?->id)),
    transportSlug: 'transport_company',
    methods: @js($deliveryMethods->map(fn ($m) => ['id' => $m->id, 'slug' => $m->slug])->values()),
    warehouseId: @js($initialWarehouseId !== null && $initialWarehouseId !== '' ? (string) $initialWarehouseId : ''),
    warehouses: @js($warehouseDetails),
    currentSlug() {
        return this.methods.find(m => Number(m.id) === Number(this.deliveryMethodId))?.slug || null;
    },
    selectedWarehouse() {
        if (!this.warehouseId) return null;
        return this.warehouses[String(this.warehouseId)] || null;
    }
}">
    <a href="{{ route('distributor.purchases.cart.index') }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-[#c3242a]">
        ← Назад в корзину
    </a>

    @if(session('success'))
        <div class="rounded-lg border border-[#c3242a]/30 bg-red-50 px-4 py-3 text-sm text-[#a01e24]">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900">
            <ul class="list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @php
        $discountAmount = (float) ($group['discount_amount'] ?? 0);
        $totalAmount = (float) $group['subtotal'];
        $listAmount = round($totalAmount + $discountAmount, 2);
    @endphp

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $manufacturer->displayName() }}</h2>
        <p class="text-sm text-gray-500 mt-1">{{ $group['items_count'] }} поз. · {{ number_format($totalAmount, 2, ',', ' ') }} ₽</p>
        @if($discountAmount > 0)
            <p class="mt-1 text-sm text-green-700">Скидка: −{{ number_format($discountAmount, 2, ',', ' ') }} ₽@if(!empty($group['discount_labels'])) · {{ implode(', ', $group['discount_labels']) }}@endif</p>
        @endif
        @if($deliveryNotes)
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-300 whitespace-pre-line">{{ $deliveryNotes }}</p>
        @endif
    </div>

    <form method="POST" action="{{ route('distributor.purchases.checkout.store', $manufacturer->id) }}" class="space-y-6">
        @csrf
        <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 space-y-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Параметры поставки</h2>
                <p class="text-xs text-gray-400">Обязательные поля отмечены <span class="text-red-500">*</span></p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Способ доставки <span class="text-red-500">*</span></label>
                    <x-styled-select name="delivery_method_id" x-model="deliveryMethodId" required>
                        @foreach($deliveryMethods as $method)
                            <option value="{{ $method->id }}" @selected((string) old('delivery_method_id', $deliveryMethods->first()?->id) === (string) $method->id)>
                                {{ $method->name }}
                            </option>
                        @endforeach
                    </x-styled-select>
                    @error('delivery_method_id')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div x-show="currentSlug() === transportSlug" x-cloak>
                    <label class="block text-xs text-gray-500 mb-1">Транспортная компания</label>
                    <x-styled-select name="transport_company_id">
                        <option value="">Выберите</option>
                        @foreach($transportCompanies as $company)
                            <option value="{{ $company->id }}" @selected((string) old('transport_company_id') === (string) $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </x-styled-select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Склад поставки <span class="text-red-500">*</span></label>
                    @if($warehouses->isEmpty())
                        <p class="text-sm text-amber-700">Нет доступного склада для оформления заказа.</p>
                    @elseif($warehouses->count() === 1)
                        <input type="hidden" name="distributor_warehouse_id" value="{{ $warehouses->first()->id }}">
                        @php $onlyWarehouse = $warehouses->first(); @endphp
                        <p class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">
                            {{ $onlyWarehouse->name }}@if($onlyWarehouse->region) · {{ $onlyWarehouse->region->name }}@endif
                        </p>
                        @if($onlyWarehouse->address || $onlyWarehouse->phone)
                            <div class="mt-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 space-y-1">
                                @if($onlyWarehouse->address)
                                    <p>{{ $onlyWarehouse->address }}</p>
                                @endif
                                @if($onlyWarehouse->phone)
                                    <p>
                                        <a href="tel:{{ $onlyWarehouse->phone }}" class="text-[#c3242a] hover:underline">{{ $onlyWarehouse->phone }}</a>
                                    </p>
                                @endif
                            </div>
                        @endif
                    @else
                        <x-styled-select name="distributor_warehouse_id" x-model="warehouseId" required>
                            <option value="">Выберите склад</option>
                            @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected((string) old('distributor_warehouse_id', $defaultWarehouseId) === (string) $warehouse->id)>
                                    {{ $warehouse->name }}@if($warehouse->region) · {{ $warehouse->region->name }}@endif
                                </option>
                            @endforeach
                        </x-styled-select>
                        <template x-if="selectedWarehouse()">
                            <div class="mt-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 space-y-1">
                                <p x-show="selectedWarehouse().region" class="text-xs text-gray-500" x-text="selectedWarehouse().region"></p>
                                <p x-show="selectedWarehouse().address" x-text="selectedWarehouse().address"></p>
                                <p x-show="selectedWarehouse().phone">
                                    <a :href="'tel:' + selectedWarehouse().phone" class="text-[#c3242a] hover:underline" x-text="selectedWarehouse().phone"></a>
                                </p>
                                <p x-show="!selectedWarehouse().address && !selectedWarehouse().phone" class="text-xs text-gray-400">Адрес и телефон не указаны</p>
                            </div>
                        </template>
                        <p x-show="!warehouseId" class="mt-1 text-xs text-gray-400">Выберите склад, с которого оформляется закупка.</p>
                        @error('distributor_warehouse_id')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    @endif
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
                    <p class="mt-1 text-xs text-gray-400">Необязательно</p>
                    @error('delivery_date')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Ответственный</label>
                    <x-styled-select name="responsible_contact_id">
                        <option value="">Не назначен</option>
                        @foreach($managers as $manager)
                            <option value="{{ $manager->id }}" @selected((string) old('responsible_contact_id') === (string) $manager->id)>{{ $manager->full_name }}</option>
                        @endforeach
                    </x-styled-select>
                </div>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Промокод</label>
                <div class="flex flex-col sm:flex-row gap-2">
                    <input type="text" name="promo_code" value="{{ old('promo_code', $appliedPromoCode ?? null) }}" maxlength="64" placeholder="Если есть"
                           class="{{ $input }} flex-1">
                    <button type="submit"
                            formaction="{{ route('distributor.purchases.checkout.promo', $manufacturer->id) }}"
                            formnovalidate
                            class="inline-flex items-center justify-center h-11 px-4 rounded-lg text-sm font-medium border border-[#c3242a] text-[#c3242a] hover:bg-red-50 whitespace-nowrap">
                        Применить
                    </button>
                </div>
                @error('promo_code')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
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
                            <td class="py-3 px-3 whitespace-nowrap">
                                @if(!empty($item['has_discount']))
                                    <p class="text-xs text-gray-400 line-through">{{ number_format($item['list_unit_price'], 2, ',', ' ') }} ₽</p>
                                    {{ number_format($item['unit_price'], 2, ',', ' ') }} ₽
                                    @if(!empty($item['discount_label']))
                                        <p class="text-xs text-green-700">{{ $item['discount_label'] }}</p>
                                    @endif
                                @else
                                    {{ number_format($item['unit_price'], 2, ',', ' ') }} ₽
                                @endif
                            </td>
                            <td class="py-3 px-3">{{ $item['quantity'] }}</td>
                            <td class="py-3 px-5 font-medium whitespace-nowrap">{{ number_format($item['line_total'], 2, ',', ' ') }} ₽</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700 space-y-1 text-sm">
                @if($discountAmount > 0)
                    <div class="flex justify-between text-gray-500">
                        <span>Сумма без скидки</span>
                        <span>{{ number_format($listAmount, 2, ',', ' ') }} ₽</span>
                    </div>
                    <div class="flex justify-between text-green-700">
                        <span>Скидка@if(!empty($group['discount_labels'])) · {{ implode(', ', $group['discount_labels']) }}@endif</span>
                        <span>−{{ number_format($discountAmount, 2, ',', ' ') }} ₽</span>
                    </div>
                @endif
                <div class="flex justify-between text-base font-semibold text-gray-900 dark:text-white pt-1">
                    <span>Итого</span>
                    <span>{{ number_format($totalAmount, 2, ',', ' ') }} ₽</span>
                </div>
            </div>
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
