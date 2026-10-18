@extends('layouts.app')

@php
    $promo = $promo ?? null;
    $isEdit = $promo !== null;
    $locked = $promo?->isLocked() ?? false;
    $canManage = $canManage ?? true;
@endphp

@section('title', $isEdit ? 'Промокод '.$promo->code : 'Новый промокод')
@section('heading', $isEdit ? 'Редактирование промокода' : 'Создание промокода')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
@endpush

@section('content')
<div class="space-y-6" x-data="{
    type: @js(old('discount_type', $promo->discount_type ?? 'percent')),
    applyAll: {{ old('apply_to_all_products', $promo->apply_to_all_products ?? true) ? 'true' : 'false' }},
    code: @js(old('code', $promo->code ?? $generatedCode ?? ''))
}">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
            <ul class="list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <a href="{{ route('manufacturer.promo-codes.index') }}" class="text-sm text-gray-500 hover:text-[#c3242a]">← К списку промокодов</a>

    <form method="POST" action="{{ $isEdit ? route('manufacturer.promo-codes.update', $promo) : route('manufacturer.promo-codes.store') }}" class="space-y-6">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
            <h2 class="text-lg font-semibold">Основные параметры</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Код промокода <span class="text-red-500">*</span></label>
                    <div class="flex gap-2">
                        <input type="text" name="code" x-model="code" maxlength="64" required @disabled($locked)
                            class="flex-1 px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm uppercase">
                        @if($canManage && ! $locked)
                            <button type="button" @click="fetch('{{ route('manufacturer.promo-codes.generate') }}').then(r => r.json()).then(d => code = d.code)"
                                class="px-3 py-2 border rounded-lg text-sm">Сгенерировать код</button>
                        @endif
                    </div>
                    @if($locked)<p class="mt-1 text-xs text-gray-500">Код нельзя изменить после начала использования.</p>@endif
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Название или описание</label>
                    <input type="text" name="name" value="{{ old('name', $promo->name ?? '') }}" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                </div>
            </div>
            <textarea name="description" rows="3" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm" placeholder="Описание">{{ old('description', $promo->description ?? '') }}</textarea>
            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="status" value="inactive">
                <input type="checkbox" name="status" value="active" @checked(old('status', $promo->status ?? 'inactive') === 'active') class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
                Активен
            </label>
        </section>

        <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
            <h2 class="text-lg font-semibold">Тип и размер скидки</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Тип скидки</label>
                    <select name="discount_type" x-model="type" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm" @disabled($locked)>
                        @foreach($types as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Размер скидки</label>
                    <input type="number" step="0.01" min="0" name="discount_value" value="{{ old('discount_value', $promo->discount_value ?? '') }}" required
                        class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm" @disabled($locked)>
                    <p class="mt-1 text-xs text-gray-500" x-text="type === 'percent' ? 'Процент от суммы подходящих товаров' : 'Фиксированная сумма в рублях'"></p>
                </div>
            </div>
        </section>

        <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
            <h2 class="text-lg font-semibold">Срок действия и ограничения</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Дата начала</label>
                    <input type="text" name="starts_at" value="{{ old('starts_at', $promo?->starts_at?->toDateString()) }}" class="js-flatpickr w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Дата окончания</label>
                    <input type="text" name="ends_at" value="{{ old('ends_at', $promo?->ends_at?->toDateString()) }}" class="js-flatpickr w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Максимальное количество использований</label>
                    <input type="number" min="1" name="max_uses" value="{{ old('max_uses', $promo->max_uses ?? '') }}" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Минимальная сумма заказа</label>
                    <input type="number" min="0" step="0.01" name="min_order_amount" value="{{ old('min_order_amount', $promo->min_order_amount ?? '') }}" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="per_user_limit" value="1" @checked(old('per_user_limit', ($promo->per_user_limit ?? 0) > 0)) class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
                Ограничение на одного пользователя
            </label>
        </section>

        <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
            <h2 class="text-lg font-semibold">Область применения</h2>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="apply_to_all_products" value="1" x-model="applyAll" class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
                Применять ко всем товарам
            </label>
            <div x-show="!applyAll" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Категории</label>
                    <x-multi-select-filter name="category_ids[]" :options="$categoryOptions" :selected="old('category_ids', $promo?->intList($promo->category_ids) ?? [])" placeholder="Категории" :searchable="true" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Регионы</label>
                    <x-multi-select-filter name="region_ids[]" :options="$regionOptions->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])" :selected="old('region_ids', $promo?->intList($promo->region_ids) ?? [])" placeholder="Регионы" :searchable="true" />
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium">Только выбранные дистрибьюторы</label>
                <x-multi-select-filter name="company_ids[]" :options="collect($distributors)" :selected="old('company_ids', $promo?->intList($promo->company_ids) ?? [])" placeholder="Все партнёры" :searchable="true" />
            </div>
        </section>

        @if($canManage)
        <div class="flex flex-wrap gap-3">
            <button type="submit" class="px-4 py-2 bg-[#c3242a] text-white rounded-lg text-sm font-medium">Сохранить</button>
            <button type="submit" name="activate" value="1" class="px-4 py-2 border border-[#c3242a] text-[#c3242a] rounded-lg text-sm">Сохранить и активировать</button>
            <a href="{{ route('manufacturer.promo-codes.index') }}" class="px-4 py-2 text-sm text-gray-500">Отмена</a>
        </div>
        @endif
    </form>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/ru.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const locale = (typeof flatpickr !== 'undefined' && flatpickr.l10ns?.ru) ? flatpickr.l10ns.ru : 'default';
    document.querySelectorAll('.js-flatpickr').forEach((el) => {
        flatpickr(el, { locale, dateFormat: 'Y-m-d', altInput: true, altFormat: 'd.m.Y', allowInput: false, disableMobile: true });
    });
});
</script>
@endpush
