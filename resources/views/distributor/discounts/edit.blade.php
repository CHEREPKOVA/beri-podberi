@extends('layouts.app')

@php
    $set = $set ?? null;
    $canManage = $canManage ?? true;
    $usedInOrders = $usedInOrders ?? false;
    $isEdit = $set !== null;
    $readonly = ! $canManage;
    $selectedProductItems = collect(old('product_ids', $set?->intList($set->product_ids) ?? []))
        ->map(fn ($id) => ['id' => (int) $id, 'label' => $selectedProducts[$id] ?? ('#'.$id)])
        ->values();
    $selectedCompanyItems = collect(old('company_ids', $set?->intList($set->company_ids) ?? []))
        ->map(fn ($id) => [
            'id' => (int) $id,
            'name' => $selectedCompanies[$id] ?? ('#'.$id),
            'inn' => collect($assignedCompanies)->firstWhere('id', (int) $id)['inn'] ?? null,
            'region' => collect($assignedCompanies)->firstWhere('id', (int) $id)['region'] ?? null,
        ])
        ->values();
@endphp

@section('title', $isEdit ? $set->name : 'Новый набор скидок')
@section('heading', $isEdit ? 'Карточка набора скидок' : 'Создание набора скидок')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
@endpush

@section('content')
<div class="space-y-6" x-data="distributorDiscountForm(@js([
    'dirty' => false,
    'type' => old('type', $set->type ?? 'products'),
    'valueKind' => old('value_kind', $set->value_kind ?? 'percent'),
    'status' => old('status', $set->status ?? 'inactive'),
    'audienceType' => old('audience_type', $set->audience_type ?? 'selected'),
    'applyAll' => (bool) old('apply_to_all_products', $set->apply_to_all_products ?? false),
    'indefinite' => (bool) old('is_indefinite', $set->is_indefinite ?? true),
    'productQuery' => '',
    'productResults' => [],
    'selectedProducts' => $selectedProductItems,
    'companyQuery' => '',
    'companyResults' => [],
    'selectedCompanies' => $selectedCompanyItems,
    'preview' => null,
    'searchUrl' => route('distributor.discounts.products.search'),
    'clientsUrl' => route('distributor.discounts.clients.search'),
    'previewUrl' => $isEdit ? route('distributor.discounts.preview', $set) : null,
    'readonly' => $readonly,
]))" @input="if (!readonly) dirty = true">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">{{ session('error') }}</div>
    @endif
    @if(session('conflict_warning'))
        <div class="bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded-lg text-sm">{{ session('conflict_warning') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
            <ul class="list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('distributor.discounts.index') }}" class="text-sm text-gray-500 hover:text-[#c3242a]" @click="leave($event, '{{ route('distributor.discounts.index') }}')">← К списку наборов</a>
        @if($isEdit && $canManage)
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('distributor.discounts.toggle', $set) }}">@csrf
                    <button class="px-3 py-2 border border-gray-300 rounded-lg text-sm">{{ $set->status === 'active' ? 'Отключить' : 'Активировать' }}</button>
                </form>
                <form method="POST" action="{{ route('distributor.discounts.copy', $set) }}">@csrf
                    <button class="px-3 py-2 border border-gray-300 rounded-lg text-sm">Копировать</button>
                </form>
            </div>
        @endif
    </div>

    @if($isEdit)
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 text-sm">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-gray-500">Тип</p>
                <p class="font-medium text-gray-900 dark:text-white">{{ $set->typeLabel() }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-gray-500">Размер</p>
                <p class="font-medium text-gray-900 dark:text-white">{{ $set->sizeLabel() }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-gray-500">Статус</p>
                <p class="font-medium text-gray-900 dark:text-white">{{ $set->statusLabel() }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-gray-500">Создан</p>
                <p class="font-medium text-gray-900 dark:text-white">{{ $set->created_at?->format('d.m.Y H:i') }} · {{ $set->createdBy?->name ?? '—' }}</p>
            </div>
        </div>
        @if($usedInOrders)
            <div class="bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded-lg text-sm">Скидка уже используется в текущих заказах. Удаление недоступно, изменение условий повлияет на новые заказы.</div>
        @endif
    @endif

    <form method="POST" action="{{ $isEdit ? route('distributor.discounts.update', $set) : route('distributor.discounts.store') }}" class="space-y-6" @submit="dirty = false">
        @csrf
        @if($isEdit) @method('PUT') @endif
        @if(session('conflict_warning'))
            <input type="hidden" name="confirm_conflicts" value="1">
        @endif
        <input type="hidden" name="status" :value="status">

        <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Основные параметры</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium">Название набора <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $set->name ?? '') }}" required maxlength="255" @disabled($readonly)
                        class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium">Краткое описание</label>
                    <textarea name="description" rows="2" @disabled($readonly)
                        class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">{{ old('description', $set->description ?? '') }}</textarea>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Тип скидки <span class="text-red-500">*</span></label>
                    <select name="type" x-model="type" @disabled($readonly) class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                        @foreach($types as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Размер скидки <span class="text-red-500">*</span></label>
                    <select name="value_kind" x-model="valueKind" @disabled($readonly) class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                        @foreach($valueKinds as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div x-show="valueKind === 'percent'">
                    <label class="mb-1.5 block text-sm font-medium">Процент, %</label>
                    <input type="number" min="0" max="100" step="0.01" name="discount_percent" value="{{ old('discount_percent', $set->discount_percent ?? '') }}" @disabled($readonly)
                        class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                </div>
                <div x-show="valueKind === 'fixed'">
                    <label class="mb-1.5 block text-sm font-medium">Фиксированная сумма, ₽</label>
                    <input type="number" min="0" step="0.01" name="discount_amount" value="{{ old('discount_amount', $set->discount_amount ?? '') }}" @disabled($readonly)
                        class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                    <p class="mt-1 text-xs text-gray-500">Итоговая цена не станет отрицательной: скидка ограничивается ценой товара.</p>
                </div>
            </div>
        </section>

        <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Период действия</h2>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_indefinite" value="1" x-model="indefinite" @disabled($readonly) class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
                Бессрочно
            </label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Дата начала</label>
                    <input type="text" name="starts_at" value="{{ old('starts_at', $set?->starts_at?->format('Y-m-d')) }}" class="js-flatpickr w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm" @disabled($readonly)>
                </div>
                <div x-show="!indefinite">
                    <label class="mb-1.5 block text-sm font-medium">Дата окончания</label>
                    <input type="text" name="ends_at" value="{{ old('ends_at', $set?->ends_at?->format('Y-m-d')) }}" class="js-flatpickr w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm" @disabled($readonly)>
                </div>
            </div>
            <p class="text-xs text-gray-500">После даты окончания система автоматически переведёт набор в статус «Завершена».</p>
        </section>

        <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Выбор товаров</h2>

            <div x-show="type === 'category'">
                <label class="mb-1.5 block text-sm font-medium">Категории</label>
                <select name="category_ids[]" multiple size="8" @disabled($readonly) class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                    @foreach($categoryOptions as $option)
                        <option value="{{ $option['value'] }}" @selected(in_array((int) $option['value'], array_map('intval', old('category_ids', $set?->intList($set->category_ids) ?? [])), true))>{{ $option['label'] }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">Все товары выбранных категорий автоматически попадут в набор.</p>
            </div>

            <div x-show="type === 'brand'">
                <label class="mb-1.5 block text-sm font-medium">Бренды</label>
                <select name="brands[]" multiple size="8" @disabled($readonly) class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                    @foreach($brandOptions as $brand)
                        <option value="{{ $brand }}" @selected(in_array($brand, old('brands', $set?->stringList($set->brands) ?? []), true))>{{ $brand }}</option>
                    @endforeach
                </select>
            </div>

            <div x-show="['products', 'personal', 'mass'].includes(type)">
                <label class="inline-flex items-center gap-2 text-sm mb-3" x-show="type !== 'products'">
                    <input type="checkbox" name="apply_to_all_products" value="1" x-model="applyAll" @disabled($readonly) class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
                    На все товары каталога
                </label>
                <div x-show="type === 'products' || !applyAll">
                    <label class="mb-1.5 block text-sm font-medium">Поиск товаров</label>
                    <input type="text" x-model="productQuery" @input.debounce.300ms="searchProducts()" placeholder="Название или артикул" @disabled($readonly)
                        class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                    <div class="mt-2 space-y-1" x-show="productResults.length">
                        <template x-for="item in productResults" :key="item.id">
                            <button type="button" @click="addProduct(item)" class="w-full text-left px-3 py-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 text-sm">
                                <span x-text="item.label"></span>
                                <span class="text-gray-400" x-text="item.price ? (' · ' + formatMoney(item.price)) : ''"></span>
                            </button>
                        </template>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <template x-for="item in selectedProducts" :key="item.id">
                            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-700 text-sm">
                                <span x-text="item.label"></span>
                                <input type="hidden" name="product_ids[]" :value="item.id">
                                <button type="button" @click="removeProduct(item.id)" x-show="!readonly">&times;</button>
                            </span>
                        </template>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4" x-show="type === 'personal' || type === 'mass'">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Назначение клиентам</h2>
            <div x-show="type === 'mass'">
                <label class="mb-1.5 block text-sm font-medium">Вариант назначения</label>
                <select name="audience_type" x-model="audienceType" @disabled($readonly) class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                    <option value="selected">Несколько компаний</option>
                    <option value="region">Клиенты региона</option>
                    <option value="company_types">Клиенты определённого типа</option>
                </select>
            </div>
            <div x-show="type === 'personal' || audienceType === 'selected'">
                <label class="mb-1.5 block text-sm font-medium">Поиск клиентов</label>
                <input type="text" x-model="companyQuery" @input.debounce.300ms="searchClients()" placeholder="Название, ИНН, регион, менеджер" @disabled($readonly)
                    class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                <div class="mt-2 space-y-1" x-show="companyResults.length">
                    <template x-for="item in companyResults" :key="item.id">
                        <button type="button" @click="addCompany(item)" class="w-full text-left px-3 py-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 text-sm">
                            <span x-text="item.name"></span>
                            <span class="text-gray-400" x-text="(item.inn ? ' · ИНН ' + item.inn : '') + (item.region ? ' · ' + item.region : '')"></span>
                        </button>
                    </template>
                </div>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm" x-show="selectedCompanies.length">
                        <thead><tr class="text-left text-xs text-gray-500 uppercase"><th class="py-2">Компания</th><th>ИНН</th><th>Регион</th><th></th></tr></thead>
                        <tbody>
                            <template x-for="item in selectedCompanies" :key="item.id">
                                <tr class="border-t border-gray-100 dark:border-gray-700">
                                    <td class="py-2"><span x-text="item.name"></span><input type="hidden" name="company_ids[]" :value="item.id"></td>
                                    <td x-text="item.inn || '—'"></td>
                                    <td x-text="item.region || '—'"></td>
                                    <td><button type="button" class="text-red-600" @click="removeCompany(item.id)" x-show="!readonly">Удалить</button></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
            <div x-show="type === 'mass' && audienceType === 'region'">
                <label class="mb-1.5 block text-sm font-medium">Регионы клиентов</label>
                <select name="region_ids[]" multiple size="6" @disabled($readonly) class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                    @foreach($regionOptions as $region)
                        <option value="{{ $region->id }}" @selected(in_array((int) $region->id, array_map('intval', old('region_ids', $set?->intList($set->region_ids) ?? [])), true))>{{ $region->name }}</option>
                    @endforeach
                </select>
            </div>
            <div x-show="type === 'mass' && audienceType === 'company_types'">
                <label class="mb-1.5 block text-sm font-medium">Типы компаний</label>
                @foreach($companyTypes as $value => $label)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="company_types[]" value="{{ $value }}" @checked(in_array($value, old('company_types', $set->company_types ?? []), true)) @disabled($readonly) class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </section>

        <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Дополнительные условия</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Минимальная сумма заказа, ₽</label>
                    <input type="number" min="0" step="0.01" name="min_order_amount" value="{{ old('min_order_amount', $set->min_order_amount ?? '') }}" @disabled($readonly)
                        class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Минимальное количество</label>
                    <input type="number" min="1" name="min_quantity" value="{{ old('min_quantity', $set->min_quantity ?? '') }}" @disabled($readonly)
                        class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Приоритет</label>
                    <input type="number" min="0" max="1000" name="priority" value="{{ old('priority', $set->priority ?? 0) }}" @disabled($readonly)
                        class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium">Склады</label>
                <select name="warehouse_ids[]" multiple size="4" @disabled($readonly) class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected(in_array((int) $warehouse->id, array_map('intval', old('warehouse_ids', $set?->intList($set->warehouse_ids) ?? [])), true))>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">Пустой список — скидка действует на любом складе.</p>
            </div>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="do_not_stack" value="1" @checked(old('do_not_stack', $set->do_not_stack ?? false)) @disabled($readonly) class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
                Не совмещать с другими скидками
            </label>
        </section>

        @if($isEdit)
            <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Товары, на которые распространяется скидка</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 uppercase">
                                <th class="py-2">Название</th>
                                <th>Артикул</th>
                                <th>Бренд</th>
                                <th>Категория</th>
                                <th>Старая цена</th>
                                <th>Новая цена</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($coveredProducts as $product)
                                <tr class="border-t border-gray-100 dark:border-gray-700">
                                    <td class="py-2">{{ $product['name'] }}</td>
                                    <td>{{ $product['sku'] ?: '—' }}</td>
                                    <td>{{ $product['brand'] ?: '—' }}</td>
                                    <td>{{ $product['category'] ?: '—' }}</td>
                                    <td>{{ number_format($product['list_price'], 2, ',', ' ') }} ₽</td>
                                    <td class="font-medium">{{ number_format($product['discounted_price'], 2, ',', ' ') }} ₽</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-4 text-gray-500">Товары появятся после сохранения условий набора.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            @if(in_array($set->type, ['personal', 'mass'], true) && $assignedCompanies !== [])
                <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Компании, на которых действует скидка</h2>
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 uppercase">
                                <th class="py-2">Компания</th>
                                <th>ИНН</th>
                                <th>Регион</th>
                                <th>Дата назначения</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($assignedCompanies as $company)
                                <tr class="border-t border-gray-100 dark:border-gray-700">
                                    <td class="py-2">{{ $company['name'] }}</td>
                                    <td>{{ $company['inn'] ?: '—' }}</td>
                                    <td>{{ $company['region'] ?: '—' }}</td>
                                    <td>{{ $set->updated_at?->format('d.m.Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endif

            <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Журнал изменений</h2>
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
                <a href="{{ route('distributor.discounts.index') }}" @click="leave($event, '{{ route('distributor.discounts.index') }}')" class="px-4 py-2 border border-gray-300 rounded-lg text-sm">Отмена</a>
                @if($isEdit && $canManage && ! $usedInOrders)
                    <button type="button" onclick="if (confirm('Удалить набор скидок?')) document.getElementById('delete-set-form').submit()" class="px-4 py-2 border border-red-300 text-red-700 rounded-lg text-sm">Удалить</button>
                @elseif($isEdit && $usedInOrders)
                    <span class="self-center text-xs text-gray-500">Удаление недоступно: набор используется в текущих заказах.</span>
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

    @if($isEdit && $canManage && ! $usedInOrders)
        <form id="delete-set-form" method="POST" action="{{ route('distributor.discounts.destroy', $set) }}" class="hidden">@csrf @method('DELETE')</form>
    @endif
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/ru.js"></script>
<script>
function distributorDiscountForm(config) {
    return {
        ...config,
        async searchProducts() {
            const q = this.productQuery.trim();
            if (q.length < 1) { this.productResults = []; return; }
            const res = await fetch(this.searchUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
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
        async searchClients() {
            const q = this.companyQuery.trim();
            if (q.length < 1) { this.companyResults = []; return; }
            const res = await fetch(this.clientsUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
            this.companyResults = await res.json();
        },
        addCompany(item) {
            if (this.type === 'personal') {
                this.selectedCompanies = [item];
            } else if (!this.selectedCompanies.some(p => Number(p.id) === Number(item.id))) {
                this.selectedCompanies.push(item);
            }
            this.companyQuery = '';
            this.companyResults = [];
            this.dirty = true;
        },
        removeCompany(id) {
            this.selectedCompanies = this.selectedCompanies.filter(p => Number(p.id) !== Number(id));
            this.dirty = true;
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
