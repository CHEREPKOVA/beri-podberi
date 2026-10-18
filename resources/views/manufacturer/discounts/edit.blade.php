@extends('layouts.app')

@php
    $set = $set ?? null;
    $canManage = $canManage ?? true;
    $canDelete = $canDelete ?? false;
    $usedInOrders = $usedInOrders ?? false;
    $isEdit = $set !== null;
    $readonly = ! $canManage;
    $oldTiers = old('tiers', $set?->normalizedTiers() ?: [['min_amount' => '', 'min_quantity' => '', 'percent' => '', 'amount' => '']]);
@endphp

@section('title', $isEdit ? $set->name : 'Новый набор скидок')
@section('heading', $isEdit ? 'Карточка набора скидок' : 'Создание набора скидок')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
@endpush

@section('content')
<div class="space-y-6" x-data="discountSetForm(@js([
    'dirty' => false,
    'type' => old('type', $set->type ?? 'percent'),
    'status' => old('status', $set->status ?? 'inactive'),
    'audienceType' => old('audience_type', $set->audience_type ?? 'all'),
    'applyAll' => (bool) old('apply_to_all_products', $set->apply_to_all_products ?? true),
    'indefinite' => (bool) old('is_indefinite', $set->is_indefinite ?? true),
    'productQuery' => '',
    'productResults' => [],
    'selectedProducts' => collect(old('product_ids', $set?->intList($set->product_ids) ?? []))->map(fn ($id) => ['id' => (int) $id, 'label' => $selectedProducts[$id] ?? ('#'.$id)])->values(),
    'distributorQuery' => '',
    'distributorRegion' => '',
    'distributorStatus' => '',
    'distributors' => $distributors,
    'selectedDistributors' => array_map('strval', old('distributor_ids', $set?->intList($set->distributor_ids) ?? [])),
    'preview' => null,
    'previewLoading' => false,
    'searchUrl' => route('manufacturer.discounts.products.search'),
    'previewUrl' => $isEdit ? route('manufacturer.discounts.preview', $set) : null,
    'readonly' => $readonly,
]))" @input="if (!readonly) dirty = true">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">{{ session('error') }}</div>
    @endif
    @if(session('conflict_warning'))
        <div class="bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded-lg text-sm">
            {{ session('conflict_warning') }}
        </div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
            <ul class="list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('manufacturer.discounts.index') }}" class="text-sm text-gray-500 hover:text-[#c3242a]" @click="leave($event, '{{ route('manufacturer.discounts.index') }}')">← К списку наборов</a>
        @if($isEdit && $canManage)
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('manufacturer.discounts.copy', $set) }}">@csrf<button class="px-3 py-2 border border-gray-300 rounded-lg text-sm">Копировать набор</button></form>
                <form method="POST" action="{{ route('manufacturer.discounts.toggle', $set) }}">@csrf<button class="px-3 py-2 border border-gray-300 rounded-lg text-sm">{{ $set->effectiveStatus() === 'active' ? 'Отключить' : 'Включить' }}</button></form>
            </div>
        @endif
    </div>

    <form method="POST" action="{{ $isEdit ? route('manufacturer.discounts.update', $set) : route('manufacturer.discounts.store') }}" class="space-y-6" @submit="dirty = false">
        @csrf
        @if($isEdit) @method('PUT') @endif
        @if(session('conflict_warning'))
            <input type="hidden" name="confirm_conflicts" value="1">
        @endif

        <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Основная информация</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium">Название набора скидок <span class="text-red-500">*</span></label>
                    <input name="name" value="{{ old('name', $set->name ?? '') }}" required maxlength="255" @disabled($readonly)
                        class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium">Описание</label>
                    <textarea name="description" rows="3" @disabled($readonly) class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">{{ old('description', $set->description ?? '') }}</textarea>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Статус</label>
                    <select name="status" x-model="status" @disabled($readonly) class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                        <option value="active">Активен</option>
                        <option value="inactive">Отключен</option>
                    </select>
                </div>
            </div>
        </section>

        <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Тип скидки</h2>
            <select name="type" x-model="type" @disabled($readonly) class="w-full md:w-1/2 px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                @foreach($types as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4" x-show="type !== 'volume_tier'">
                <div x-show="type !== 'fixed'">
                    <label class="mb-1.5 block text-sm font-medium">Процент скидки (%)</label>
                    <input type="number" step="0.01" min="0" max="100" name="discount_percent" value="{{ old('discount_percent', $set->discount_percent ?? '') }}" @disabled($readonly)
                        class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                </div>
                <div x-show="type === 'fixed' || type === 'percent'">
                    <label class="mb-1.5 block text-sm font-medium">Фиксированная сумма скидки (₽)</label>
                    <input type="number" step="0.01" min="0" name="discount_amount" value="{{ old('discount_amount', $set->discount_amount ?? '') }}" @disabled($readonly)
                        class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                </div>
            </div>
            <div x-show="type === 'volume_tier'" x-cloak class="space-y-3">
                <p class="text-sm text-gray-500">Градации: порог суммы или количества → процент или сумма.</p>
                @foreach($oldTiers as $i => $tier)
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                        <input type="number" step="0.01" name="tiers[{{ $i }}][min_amount]" value="{{ $tier['min_amount'] ?? '' }}" placeholder="От суммы, ₽" @disabled($readonly) class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700">
                        <input type="number" name="tiers[{{ $i }}][min_quantity]" value="{{ $tier['min_quantity'] ?? '' }}" placeholder="От кол-ва" @disabled($readonly) class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700">
                        <input type="number" step="0.01" name="tiers[{{ $i }}][percent]" value="{{ $tier['percent'] ?? '' }}" placeholder="%" @disabled($readonly) class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700">
                        <input type="number" step="0.01" name="tiers[{{ $i }}][amount]" value="{{ $tier['amount'] ?? '' }}" placeholder="Сумма, ₽" @disabled($readonly) class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700">
                    </div>
                @endforeach
            </div>
        </section>

        <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Условия применения скидки</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Минимальная сумма заказа</label>
                    <input type="number" step="0.01" min="0" name="min_order_amount" value="{{ old('min_order_amount', $set->min_order_amount ?? '') }}" @disabled($readonly) class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Минимальное количество товаров</label>
                    <input type="number" min="1" name="min_quantity" value="{{ old('min_quantity', $set->min_quantity ?? '') }}" @disabled($readonly) class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="require_all_conditions" value="1" @checked(old('require_all_conditions', $set->require_all_conditions ?? true)) @disabled($readonly) class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]"> Применять только при выполнении всех условий</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="apply_only_active_products" value="1" @checked(old('apply_only_active_products', $set->apply_only_active_products ?? true)) @disabled($readonly) class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]"> Применять только к активным товарам</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="only_new_distributors" value="1" @checked(old('only_new_distributors', $set->only_new_distributors ?? false)) @disabled($readonly) class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]"> Только для новых дистрибьюторов</label>

            <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="apply_to_all_products" value="1" x-model="applyAll" @disabled($readonly) class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]"> Применять ко всем товарам</label>

            <div x-show="!applyAll" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Категории товаров</label>
                    <x-multi-select-filter name="category_ids[]" :options="$categoryOptions" :selected="old('category_ids', $set?->intList($set->category_ids) ?? [])" placeholder="Категории" :searchable="true" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Исключения из категорий</label>
                    <x-multi-select-filter name="excluded_category_ids[]" :options="$categoryOptions" :selected="old('excluded_category_ids', $set?->intList($set->excluded_category_ids) ?? [])" placeholder="Исключения" :searchable="true" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium">Отдельные товары</label>
                    <div class="relative">
                        <input type="search" x-model="productQuery" @input.debounce.300ms="searchProducts()" placeholder="Поиск товара по названию или артикулу" @disabled($readonly)
                            class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                        <div x-show="productResults.length" x-cloak class="absolute z-20 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg shadow max-h-56 overflow-y-auto">
                            <template x-for="item in productResults" :key="item.id">
                                <button type="button" class="block w-full text-left px-3 py-2 text-sm hover:bg-gray-50" @click="addProduct(item)" x-text="item.label"></button>
                            </template>
                        </div>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <template x-for="item in selectedProducts" :key="item.id">
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-gray-100 dark:bg-gray-700 text-xs">
                                <input type="hidden" name="product_ids[]" :value="item.id">
                                <span x-text="item.label"></span>
                                <button type="button" class="text-gray-400" @click="removeProduct(item.id)" x-show="!readonly">×</button>
                            </span>
                        </template>
                    </div>
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium">Регион действия скидки</label>
                <x-multi-select-filter name="region_ids[]" :options="$regionOptions->map(fn ($r) => ['value' => $r->id, 'label' => $r->name])" :selected="old('region_ids', $set?->intList($set->region_ids) ?? [])" placeholder="Все регионы" :searchable="true" />
            </div>
        </section>

        <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Привязка к дистрибьюторам</h2>
            <select name="audience_type" x-model="audienceType" @disabled($readonly) class="w-full md:w-1/2 px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                @foreach($audienceTypes as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <div x-show="audienceType === 'company_types'" x-cloak>
                <x-multi-select-filter name="company_types[]" :options="collect($companyTypes)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])" :selected="old('company_types', $set->company_types ?? [])" placeholder="Типы компаний" />
            </div>
            <div x-show="audienceType === 'region'" x-cloak>
                <label class="mb-1.5 block text-sm font-medium">Регионы дистрибьюторов</label>
                <x-multi-select-filter name="audience_region_ids[]" :options="$regionOptions->map(fn ($r) => ['value' => $r->id, 'label' => $r->name])" :selected="old('audience_region_ids', $set?->intList($set->audience_region_ids) ?? [])" placeholder="Регионы" :searchable="true" />
            </div>
            <div x-show="audienceType === 'selected'" x-cloak class="space-y-3">
                <div class="flex flex-wrap gap-2">
                    <input type="search" x-model="distributorQuery" placeholder="Поиск дистрибьютора" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700">
                    <select x-model="distributorRegion" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700">
                        <option value="">Все регионы</option>
                        @foreach($regionOptions as $region)
                            <option value="{{ $region->name }}">{{ $region->name }}</option>
                        @endforeach
                    </select>
                    <select x-model="distributorStatus" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700">
                        <option value="">Любой статус</option>
                        <option value="active">Активен</option>
                        <option value="blocked">Неактивен</option>
                    </select>
                </div>
                <div class="max-h-64 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg divide-y divide-gray-100 dark:divide-gray-700">
                    <template x-for="item in filteredDistributors" :key="item.id">
                        <label class="flex items-center gap-2 px-3 py-2 text-sm">
                            <input type="checkbox" name="distributor_ids[]" :value="item.id" :checked="selectedDistributors.includes(String(item.id))" @change="toggleDistributor(item.id)" @disabled="readonly" class="rounded border-gray-300 text-[#c3242a]">
                            <span x-text="item.name"></span>
                            <span class="text-xs text-gray-400" x-text="item.region || ''"></span>
                        </label>
                    </template>
                </div>
            </div>
        </section>

        <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Временные ограничения</h2>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_indefinite" value="1" x-model="indefinite" @disabled($readonly) class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]"> Бессрочно</label>
            <label class="flex items-center gap-2 text-sm" x-show="type === 'promo'"><input type="checkbox" name="auto_deactivate" value="1" @checked(old('auto_deactivate', $set->auto_deactivate ?? true)) @disabled($readonly) class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]"> Автоматически отключать по окончании срока</label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Дата начала действия</label>
                    <input type="text" name="starts_at" value="{{ old('starts_at', $set?->starts_at?->toDateString()) }}" class="js-flatpickr w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm" @disabled($readonly)>
                </div>
                <div x-show="!indefinite">
                    <label class="mb-1.5 block text-sm font-medium">Дата окончания</label>
                    <input type="text" name="ends_at" value="{{ old('ends_at', $set?->ends_at?->toDateString()) }}" class="js-flatpickr w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm" @disabled($readonly)>
                </div>
            </div>
        </section>

        @if($isEdit)
        <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Проверка и тестирование набора скидок</h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <select x-ref="previewDistributor" class="px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                    <option value="">Дистрибьютор</option>
                    @foreach($distributors as $distributor)
                        <option value="{{ $distributor['id'] }}">{{ $distributor['name'] }}</option>
                    @endforeach
                </select>
                <select x-ref="previewProduct" class="px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                    <option value="">Товар</option>
                    @foreach($selectedProducts as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
                <input type="number" min="1" value="1" x-ref="previewQty" class="px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm" placeholder="Количество">
                <select x-ref="previewRegion" class="px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                    <option value="">Регион</option>
                    @foreach($regionOptions as $region)
                        <option value="{{ $region->id }}">{{ $region->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="button" @click="runPreview()" class="px-4 py-2 bg-gray-900 text-white rounded-lg text-sm">Проверить скидку</button>
            <div x-show="preview" x-cloak class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 text-sm space-y-1">
                <p x-text="preview?.explanation"></p>
                <p x-show="preview?.applies">Конечная цена: <span class="font-semibold" x-text="formatMoney(preview?.unit_price)"></span> (было <span x-text="formatMoney(preview?.list_unit_price)"></span>)</p>
            </div>
        </section>

        <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">История изменений</h2>
            <div class="space-y-3 text-sm">
                @forelse($set->logs as $log)
                    <div class="border-b border-gray-100 dark:border-gray-700 pb-3">
                        <p class="font-medium">{{ $log->user?->name ?? 'Система' }} · {{ $log->created_at?->format('d.m.Y H:i') }} · {{ $log->actionLabel() }}</p>
                        @if(is_array($log->changes))
                            <ul class="mt-1 text-gray-500">
                                @foreach($log->changes as $field => $pair)
                                    <li>{{ $field }}: {{ is_array($pair) ? (($pair[0] ?? '—').' → '.($pair[1] ?? '—')) : $pair }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @empty
                    <p class="text-gray-500">Записей пока нет.</p>
                @endforelse
            </div>
        </section>
        @endif

        <div class="flex flex-wrap justify-between gap-3">
            <div class="flex gap-2">
                <a href="{{ route('manufacturer.discounts.index') }}" @click="leave($event, '{{ route('manufacturer.discounts.index') }}')" class="px-4 py-2 border border-gray-300 rounded-lg text-sm">Отмена</a>
                @if($isEdit && $canDelete && ! $usedInOrders)
                    <button type="button" onclick="if (confirm('Удалить набор скидок?')) document.getElementById('delete-set-form').submit()" class="px-4 py-2 border border-red-300 text-red-700 rounded-lg text-sm">Удалить набор</button>
                @elseif($isEdit && $usedInOrders)
                    <span class="self-center text-xs text-gray-500">Удаление недоступно: набор используется в активных заказах.</span>
                @endif
            </div>
            @if($canManage)
            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 border border-gray-300 rounded-lg text-sm">Сохранить</button>
                <button type="submit" name="activate" value="1" class="px-4 py-2 bg-[#c3242a] text-white rounded-lg text-sm hover:bg-[#a01e24]">Сохранить и активировать</button>
            </div>
            @endif
        </div>
    </form>

    @if($isEdit && $canDelete && ! $usedInOrders)
        <form id="delete-set-form" method="POST" action="{{ route('manufacturer.discounts.destroy', $set) }}" class="hidden">@csrf @method('DELETE')</form>
    @endif
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/ru.js"></script>
<script>
function discountSetForm(config) {
    return {
        ...config,
        get filteredDistributors() {
            const q = (this.distributorQuery || '').toLowerCase();
            return (this.distributors || []).filter((item) => {
                if (q && !(item.name || '').toLowerCase().includes(q)) return false;
                if (this.distributorRegion && item.region !== this.distributorRegion) return false;
                if (this.distributorStatus && item.status !== this.distributorStatus) return false;
                return true;
            });
        },
        async searchProducts() {
            const q = this.productQuery.trim();
            if (q.length < 1) { this.productResults = []; return; }
            const url = this.searchUrl + '?q=' + encodeURIComponent(q);
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            this.productResults = await res.json();
        },
        addProduct(item) {
            if (!this.selectedProducts.some(p => Number(p.id) === Number(item.id))) {
                this.selectedProducts.push(item);
            }
            this.productQuery = '';
            this.productResults = [];
            this.dirty = true;
        },
        removeProduct(id) {
            this.selectedProducts = this.selectedProducts.filter(p => Number(p.id) !== Number(id));
            this.dirty = true;
        },
        toggleDistributor(id) {
            id = String(id);
            if (this.selectedDistributors.includes(id)) {
                this.selectedDistributors = this.selectedDistributors.filter(v => v !== id);
            } else {
                this.selectedDistributors = [...this.selectedDistributors, id];
            }
            this.dirty = true;
        },
        async runPreview() {
            if (!this.previewUrl) return;
            this.previewLoading = true;
            const body = new FormData();
            body.append('_token', document.querySelector('meta[name=csrf-token]')?.content || '{{ csrf_token() }}');
            body.append('distributor_id', this.$refs.previewDistributor.value);
            body.append('product_id', this.$refs.previewProduct.value);
            body.append('quantity', this.$refs.previewQty.value || 1);
            if (this.$refs.previewRegion.value) body.append('region_id', this.$refs.previewRegion.value);
            const res = await fetch(this.previewUrl, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': body.get('_token') }, body });
            this.preview = await res.json();
            this.previewLoading = false;
        },
        formatMoney(value) {
            return new Intl.NumberFormat('ru-RU', { minimumFractionDigits: 2 }).format(Number(value || 0)) + ' ₽';
        },
        leave(event, href) {
            if (this.dirty && !confirm('Изменения не сохранены. Сохранить?')) {
                event.preventDefault();
                return;
            }
            if (this.dirty) {
                event.preventDefault();
                this.dirty = false;
                this.$el.querySelector('form').submit();
            }
        }
    };
}
document.addEventListener('DOMContentLoaded', () => {
    const locale = (typeof flatpickr !== 'undefined' && flatpickr.l10ns?.ru) ? flatpickr.l10ns.ru : 'default';
    document.querySelectorAll('.js-flatpickr').forEach((el) => {
        flatpickr(el, { locale, dateFormat: 'Y-m-d', altInput: true, altFormat: 'd.m.Y', allowInput: false, disableMobile: true });
    });
});
window.addEventListener('beforeunload', (e) => {
    const root = document.querySelector('[x-data]');
    if (root && root._x_dataStack && root._x_dataStack[0]?.dirty) {
        e.preventDefault();
        e.returnValue = '';
    }
});
</script>
@endpush
