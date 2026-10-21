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
    $historyFieldLabels = [
        'name' => 'Название',
        'description' => 'Описание',
        'type' => 'Тип скидки',
        'status' => 'Статус',
        'value_kind' => 'Вид скидки',
        'discount_percent' => 'Процент скидки',
        'discount_amount' => 'Сумма скидки',
        'min_order_amount' => 'Мин. сумма заказа',
        'min_quantity' => 'Мин. количество',
        'priority' => 'Приоритет',
        'do_not_stack' => 'Не совмещать',
        'apply_to_all_products' => 'Все товары',
        'audience_type' => 'Аудитория',
        'category_ids' => 'Категории',
        'brands' => 'Бренды',
        'product_ids' => 'Товары',
        'company_ids' => 'Клиенты',
        'region_ids' => 'Регионы',
        'warehouse_ids' => 'Склады',
        'company_types' => 'Типы компаний',
        'starts_at' => 'Дата начала',
        'ends_at' => 'Дата окончания',
        'is_indefinite' => 'Бессрочно',
        'auto_deactivate' => 'Автоотключение',
        'source_id' => 'Источник копии',
    ];
    $formatHistoryValue = static function (mixed $value): string {
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'да' : 'нет';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—';
        }

        return (string) $value;
    };
    $deactivatedLog = $isEdit ? $set->logs->firstWhere('action', 'deactivated') : null;
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
    'previewLoading' => false,
    'previewQty' => 1,
    'previewProductQuery' => '',
    'previewProductResults' => [],
    'previewProduct' => null,
    'previewProductOpen' => false,
    'previewProductSearched' => false,
    'previewCompanyQuery' => '',
    'previewCompanyResults' => [],
    'previewCompany' => null,
    'previewCompanyOpen' => false,
    'searchUrl' => route('distributor.discounts.products.search'),
    'clientsUrl' => route('distributor.discounts.clients.search'),
    'previewUrl' => $isEdit ? route('distributor.discounts.preview', $set) : null,
    'readonly' => $readonly,
]))" @input="if (!readonly) dirty = true">
    @if(session('success'))
        <div class="bg-red-50 border border-[#c3242a]/30 text-[#a01e24] px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">{{ session('error') }}</div>
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
                    <button class="px-3 py-2 border border-gray-300 rounded-lg text-sm">{{ $set->status === 'active' ? 'Отключить' : 'Включить' }}</button>
                </form>
                <form method="POST" action="{{ route('distributor.discounts.copy', $set) }}">@csrf
                    <button class="px-3 py-2 border border-gray-300 rounded-lg text-sm">Копировать набор</button>
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
            <div class="bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded-lg text-sm space-y-3">
                <p>{{ session('conflict_warning') }}</p>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" name="confirm_conflicts" value="1" class="px-3 py-1.5 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800">Продолжить</button>
                    <a href="{{ $isEdit ? route('distributor.discounts.edit', $set) : route('distributor.discounts.create') }}" class="px-3 py-1.5 border border-amber-300 rounded-lg text-sm hover:bg-amber-100">Отмена</a>
                </div>
            </div>
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
                    <x-styled-select name="type" x-model="type" :disabled="$readonly">
                        @foreach($types as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-styled-select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Размер скидки <span class="text-red-500">*</span></label>
                    <x-styled-select name="value_kind" x-model="valueKind" :disabled="$readonly">
                        @foreach($valueKinds as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-styled-select>
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
            <x-brand-checkbox name="is_indefinite" value="1" x-model="indefinite" :disabled="$readonly">Бессрочно</x-brand-checkbox>
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
                @if($readonly)
                    @php
                        $selectedCategoryIds = array_map('intval', old('category_ids', $set?->intList($set->category_ids) ?? []));
                        $categoryNameById = ($categories ?? collect())->pluck('name', 'id');
                    @endphp
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        @forelse($selectedCategoryIds as $categoryId)
                            {{ $categoryNameById[$categoryId] ?? ('#'.$categoryId) }}@if(! $loop->last), @endif
                        @empty
                            Не выбраны
                        @endforelse
                    </p>
                @else
                    @include('manufacturer.products._category_tree_select', [
                        'name' => 'category_ids',
                        'multiple' => true,
                        'tree' => $categoryTree,
                        'categories' => $categories,
                        'selectedIds' => old('category_ids', $set?->intList($set->category_ids) ?? []),
                        'placeholder' => 'Выберите категории',
                        'inputId' => 'distributor-discount-category-ids',
                        'allowClear' => true,
                        'clearLabel' => 'Сбросить категории',
                    ])
                @endif
                <p class="mt-1 text-xs text-gray-500">Все товары выбранных категорий автоматически попадут в набор.</p>
            </div>

            <div x-show="type === 'brand'">
                <label class="mb-1.5 block text-sm font-medium">Бренды</label>
                <x-multi-select-filter
                    name="brands[]"
                    :options="collect($brandOptions)"
                    :selected="old('brands', $set?->stringList($set->brands) ?? [])"
                    placeholder="Выберите бренды"
                    :searchable="true"
                    :disabled="$readonly"
                />
            </div>

            <div x-show="['products', 'personal', 'mass'].includes(type)">
                <div class="mb-3" x-cloak>
                    <x-brand-checkbox name="apply_to_all_products" value="1" x-model="applyAll" :disabled="$readonly">На все товары каталога</x-brand-checkbox>
                </div>
                <div x-show="!applyAll">
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
                <x-styled-select name="audience_type" x-model="audienceType" :disabled="$readonly">
                    <option value="selected">Несколько компаний</option>
                    <option value="region">Клиенты региона</option>
                    <option value="company_types">Клиенты определённого типа</option>
                </x-styled-select>
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
                <x-multi-select-filter
                    name="region_ids[]"
                    :options="$regionOptions"
                    :selected="old('region_ids', $set?->intList($set->region_ids) ?? [])"
                    placeholder="Выберите регионы"
                    :searchable="true"
                    :disabled="$readonly"
                />
            </div>
            <div x-show="type === 'mass' && audienceType === 'company_types'">
                <label class="mb-1.5 block text-sm font-medium">Типы компаний</label>
                <div class="space-y-2">
                    @foreach($companyTypes as $value => $label)
                        <x-brand-checkbox name="company_types[]" value="{{ $value }}" :checked="in_array($value, old('company_types', $set->company_types ?? []), true)" :disabled="$readonly">{{ $label }}</x-brand-checkbox>
                    @endforeach
                </div>
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
                <x-multi-select-filter
                    name="warehouse_ids[]"
                    :options="$warehouses"
                    :selected="old('warehouse_ids', $set?->intList($set->warehouse_ids) ?? [])"
                    placeholder="Любой склад"
                    :searchable="true"
                    :disabled="$readonly"
                />
                <p class="mt-1 text-xs text-gray-500">Пустой список — скидка действует на любом складе.</p>
            </div>
            <x-brand-checkbox name="do_not_stack" value="1" :checked="old('do_not_stack', $set->do_not_stack ?? false)" :disabled="$readonly">Не совмещать с другими скидками</x-brand-checkbox>
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

            @if($canManage)
            <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Проверка и тестирование набора скидок</h2>
                <p class="text-sm text-gray-500">Выберите клиента и товар (поиск по названию или артикулу), укажите количество и при необходимости регион/склад. Проверка использует текущие значения на форме, даже если набор ещё не сохранён.</p>
                <div class="grid grid-cols-1 md:grid-cols-[minmax(0,1.3fr)_minmax(0,1.5fr)_6.5rem_minmax(0,1fr)_minmax(0,1fr)] gap-3 items-start">
                    <div class="relative" @click.outside="previewCompanyOpen = false">
                        <label class="mb-1.5 block text-sm font-medium">Клиент</label>
                        <div class="relative">
                            <input type="search" x-model="previewCompanyQuery" @focus="openPreviewCompanySearch()" @input.debounce.300ms="onPreviewCompanyQueryInput()"
                                placeholder="Название или ИНН" autocomplete="off"
                                class="w-full pl-3 pr-10 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                            <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </div>
                        <div x-show="previewCompanyOpen" x-cloak class="absolute z-30 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                            <template x-for="item in previewCompanyResults" :key="'pc-'+item.id">
                                <button type="button" class="block w-full text-left px-3 py-2.5 text-sm hover:bg-red-50 dark:hover:bg-red-900/20" @click="selectPreviewCompany(item)">
                                    <span x-text="item.name"></span>
                                    <span class="text-gray-400" x-text="(item.inn ? ' · ИНН ' + item.inn : '') + (item.region ? ' · ' + item.region : '')"></span>
                                </button>
                            </template>
                            <p class="px-3 py-3 text-sm text-gray-500" x-show="previewCompanyResults.length === 0">Начните вводить название или ИНН.</p>
                        </div>
                        <div class="mt-2" x-show="previewCompany" x-cloak>
                            <span class="inline-flex items-center gap-2 max-w-full px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-700 text-xs">
                                <span class="truncate" x-text="previewCompany?.name"></span>
                                <button type="button" class="shrink-0 text-gray-400" @click="clearPreviewCompany()">×</button>
                            </span>
                        </div>
                    </div>
                    <div class="relative" @click.outside="previewProductOpen = false">
                        <label class="mb-1.5 block text-sm font-medium">Товар</label>
                        <div class="relative">
                            <input type="search" x-model="previewProductQuery" @focus="openPreviewProductSearch()" @input.debounce.300ms="onPreviewProductQueryInput()"
                                placeholder="Название или артикул" autocomplete="off"
                                class="w-full pl-3 pr-10 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                            <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </div>
                        <div x-show="previewProductOpen" x-cloak class="absolute z-30 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                            <template x-for="item in previewProductResults" :key="'pp-'+item.id">
                                <button type="button" class="block w-full text-left px-3 py-2.5 text-sm hover:bg-red-50 dark:hover:bg-red-900/20" @click="selectPreviewProduct(item)" x-text="item.label"></button>
                            </template>
                            <p class="px-3 py-3 text-sm text-gray-500" x-show="previewProductSearched && previewProductResults.length === 0">Ничего не найдено.</p>
                            <p class="px-3 py-3 text-sm text-gray-500" x-show="!previewProductSearched && previewProductResults.length === 0">Начните вводить название или артикул.</p>
                        </div>
                        <div class="mt-2" x-show="previewProduct" x-cloak>
                            <span class="inline-flex items-center gap-2 max-w-full px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-700 text-xs">
                                <span class="truncate" x-text="previewProduct?.label"></span>
                                <button type="button" class="shrink-0 text-gray-400" @click="clearPreviewProduct()">×</button>
                            </span>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Кол-во</label>
                        <input type="number" min="1" step="1" x-model.number="previewQty"
                            class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Регион</label>
                        <x-styled-select x-ref="previewRegion">
                            <option value="">Не выбран</option>
                            @foreach($regionOptions as $region)
                                <option value="{{ $region->id }}">{{ $region->name }}</option>
                            @endforeach
                        </x-styled-select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Склад</label>
                        <x-styled-select x-ref="previewWarehouse">
                            <option value="">Любой</option>
                            @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                            @endforeach
                        </x-styled-select>
                    </div>
                </div>
                <button type="button" @click="runPreview()" class="px-4 py-2 bg-gray-900 text-white rounded-lg text-sm" :disabled="previewLoading">Проверить скидку</button>
                <div x-show="preview" x-cloak class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 text-sm space-y-1">
                    <p x-text="preview?.explanation"></p>
                    <p x-show="preview?.applies">Конечная цена: <span class="font-semibold" x-text="formatMoney(preview?.unit_price)"></span> (было <span x-text="formatMoney(preview?.list_unit_price)"></span>)</p>
                </div>
            </section>
            @endif

            <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Журнал изменений</h2>
                <div class="mb-4 grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                    <div class="rounded-lg border border-gray-100 dark:border-gray-700 p-3">
                        <p class="text-gray-500">Дата создания</p>
                        <p class="font-medium">{{ $set->created_at?->format('d.m.Y H:i') ?? '—' }} · {{ $set->createdBy?->name ?? 'Система' }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-100 dark:border-gray-700 p-3">
                        <p class="text-gray-500">Дата деактивации</p>
                        <p class="font-medium">
                            @if($deactivatedLog)
                                {{ $deactivatedLog->created_at?->format('d.m.Y H:i') }} · {{ $deactivatedLog->user?->name ?? 'Система' }}
                            @else
                                —
                            @endif
                        </p>
                    </div>
                </div>
                <div class="space-y-3 text-sm">
                    @forelse($set->logs as $log)
                        <div class="border-b border-gray-100 dark:border-gray-700 pb-3">
                            <p class="font-medium">{{ $log->user?->name ?? 'Система' }} · {{ $log->created_at?->format('d.m.Y H:i') }} · {{ $log->actionLabel() }}</p>
                            @if(is_array($log->changes))
                                <ul class="mt-1 text-gray-500">
                                    @foreach($log->changes as $field => $pair)
                                        @php
                                            $label = $historyFieldLabels[$field] ?? $field;
                                            $from = is_array($pair) ? ($pair[0] ?? null) : null;
                                            $to = is_array($pair) ? ($pair[1] ?? null) : $pair;
                                        @endphp
                                        <li>{{ $label }}: {{ $formatHistoryValue($from) }} → {{ $formatHistoryValue($to) }}</li>
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
        async openPreviewProductSearch() {
            this.previewProductOpen = true;
            if (this.previewProductResults.length === 0) {
                await this.searchPreviewProducts(true);
            }
        },
        async onPreviewProductQueryInput() {
            if (this.previewProduct && this.previewProductQuery !== this.previewProduct.label) {
                this.previewProduct = null;
            }
            await this.searchPreviewProducts();
        },
        async searchPreviewProducts(force = false) {
            const q = (this.previewProductQuery || '').trim();
            this.previewProductOpen = true;
            const res = await fetch(this.searchUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
            this.previewProductResults = await res.json();
            this.previewProductSearched = q.length > 0 || force;
        },
        selectPreviewProduct(item) {
            this.previewProduct = item;
            this.previewProductQuery = item.label;
            this.previewProductResults = [];
            this.previewProductOpen = false;
            this.previewProductSearched = false;
        },
        clearPreviewProduct() {
            this.previewProduct = null;
            this.previewProductQuery = '';
            this.previewProductResults = [];
            this.previewProductOpen = false;
            this.previewProductSearched = false;
        },
        async openPreviewCompanySearch() {
            this.previewCompanyOpen = true;
            if (this.previewCompanyResults.length === 0) {
                await this.searchPreviewCompanies();
            }
        },
        async onPreviewCompanyQueryInput() {
            if (this.previewCompany && this.previewCompanyQuery !== this.previewCompany.name) {
                this.previewCompany = null;
            }
            await this.searchPreviewCompanies();
        },
        async searchPreviewCompanies() {
            const q = (this.previewCompanyQuery || '').trim();
            this.previewCompanyOpen = true;
            const res = await fetch(this.clientsUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
            this.previewCompanyResults = await res.json();
        },
        selectPreviewCompany(item) {
            this.previewCompany = item;
            this.previewCompanyQuery = item.name;
            this.previewCompanyResults = [];
            this.previewCompanyOpen = false;
        },
        clearPreviewCompany() {
            this.previewCompany = null;
            this.previewCompanyQuery = '';
            this.previewCompanyResults = [];
            this.previewCompanyOpen = false;
        },
        async runPreview() {
            if (!this.previewUrl) return;
            if (!this.previewCompany?.id) {
                this.preview = { applies: false, explanation: 'Выберите клиента для проверки.' };
                return;
            }
            if (!this.previewProduct?.id) {
                this.preview = { applies: false, explanation: 'Найдите и выберите товар: введите название или артикул.' };
                return;
            }
            this.previewLoading = true;
            const body = new FormData();
            const form = this.$el.querySelector('form');
            if (form) {
                const skip = new Set(['_method', 'activate', 'confirm_conflicts']);
                for (const [key, value] of new FormData(form).entries()) {
                    if (skip.has(key)) continue;
                    body.append(key, value);
                }
            }
            body.set('_token', document.querySelector('meta[name=csrf-token]')?.content || '{{ csrf_token() }}');
            body.set('company_id', String(this.previewCompany.id));
            body.set('product_id', String(this.previewProduct.id));
            body.set('quantity', String(Math.max(1, Number(this.previewQty) || 1)));
            const selectValue = (ref) => ref?.querySelector?.('select')?.value ?? ref?.value ?? '';
            const regionId = selectValue(this.$refs.previewRegion);
            const warehouseId = selectValue(this.$refs.previewWarehouse);
            if (regionId) body.set('region_id', regionId); else body.delete('region_id');
            if (warehouseId) body.set('warehouse_id', warehouseId); else body.delete('warehouse_id');
            body.set('type', this.type);
            body.set('value_kind', this.valueKind);
            body.set('audience_type', this.type === 'personal' ? 'selected' : this.audienceType);
            body.set('apply_to_all_products', this.applyAll ? '1' : '0');
            body.set('is_indefinite', this.indefinite ? '1' : '0');
            const checked = Boolean(form?.querySelector('input[name="do_not_stack"]:checked'));
            body.set('do_not_stack', checked ? '1' : '0');
            if (this.applyAll && this.type !== 'category' && this.type !== 'brand') {
                ['product_ids[]', 'product_ids'].forEach((key) => body.delete(key));
            }
            if (this.type === 'personal' || this.audienceType === 'selected') {
                body.delete('company_ids[]');
                body.delete('company_ids');
                this.selectedCompanies.forEach((item) => body.append('company_ids[]', item.id));
            }
            const res = await fetch(this.previewUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': body.get('_token') },
                body,
            });
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
