@extends('layouts.app')

@php
    $set = $set ?? null;
    $canManage = $canManage ?? true;
    $canDelete = $canDelete ?? false;
    $usedInOrders = $usedInOrders ?? false;
    $isEdit = $set !== null;
    $readonly = ! $canManage;
    $oldTiers = old('tiers', $set?->normalizedTiers() ?: [['min_amount' => '', 'min_quantity' => '', 'percent' => '', 'amount' => '']]);
    $tiersForJs = collect($oldTiers)->map(fn ($tier) => [
        'min_amount' => $tier['min_amount'] ?? '',
        'min_quantity' => $tier['min_quantity'] ?? '',
        'percent' => $tier['percent'] ?? '',
        'amount' => $tier['amount'] ?? '',
    ])->values()->all();
    $selectedCategoryIds = array_map('intval', old('category_ids', $set?->intList($set->category_ids) ?? []));
    $excludedCategoryIds = array_map('intval', old('excluded_category_ids', $set?->intList($set->excluded_category_ids) ?? []));
    $categoryNameById = ($categories ?? collect())->pluck('name', 'id');
    $historyFieldLabels = [
        'name' => 'Название',
        'description' => 'Описание',
        'type' => 'Тип скидки',
        'status' => 'Статус',
        'discount_percent' => 'Процент скидки',
        'discount_amount' => 'Сумма скидки',
        'min_order_amount' => 'Мин. сумма заказа',
        'min_quantity' => 'Мин. количество',
        'require_all_conditions' => 'Все условия',
        'apply_to_all_products' => 'Все товары',
        'apply_only_active_products' => 'Только активные товары',
        'only_new_distributors' => 'Только новые дистрибьюторы',
        'audience_type' => 'Аудитория',
        'category_ids' => 'Категории',
        'excluded_category_ids' => 'Исключения категорий',
        'product_ids' => 'Товары',
        'region_ids' => 'Регионы действия',
        'audience_region_ids' => 'Регионы дистрибьюторов',
        'distributor_ids' => 'Дистрибьюторы',
        'company_types' => 'Типы компаний',
        'tiers' => 'Градации',
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
    $deactivatedLog = $isEdit
        ? $set->logs->firstWhere('action', 'deactivated')
        : null;
@endphp

@section('title', $isEdit ? $set->name : 'Новый набор скидок')
@section('heading', $isEdit ? 'Карточка набора скидок' : 'Создание набора скидок')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
@endpush

@section('content')
<div class="space-y-6" x-data="discountSetForm(@js([
    'dirty' => false,
    'showDeleteModal' => false,
    'showDistributorModal' => false,
    'type' => old('type', $set->type ?? 'percent'),
    'status' => old('status', $set->status ?? 'active'),
    'audienceType' => old('audience_type', $set->audience_type ?? 'all'),
    'applyAll' => (bool) old('apply_to_all_products', $set->apply_to_all_products ?? true),
    'indefinite' => (bool) old('is_indefinite', $set->is_indefinite ?? true),
    'tiers' => $tiersForJs,
    'productQuery' => '',
    'productResults' => [],
    'selectedProducts' => collect(old('product_ids', $set?->intList($set->product_ids) ?? []))->map(fn ($id) => ['id' => (int) $id, 'label' => $selectedProducts[$id] ?? ('#'.$id)])->values(),
    'distributorQuery' => '',
    'distributorRegion' => '',
    'distributorStatus' => '',
    'modalSelected' => [],
    'distributors' => $distributors,
    'selectedDistributors' => array_map('strval', old('distributor_ids', $set?->intList($set->distributor_ids) ?? [])),
    'preview' => null,
    'previewLoading' => false,
    'previewQty' => 1,
    'previewProductQuery' => '',
    'previewProductResults' => [],
    'previewProduct' => null,
    'previewProductOpen' => false,
    'previewProductSearched' => false,
    'searchUrl' => route('manufacturer.discounts.products.search'),
    'previewUrl' => $isEdit ? route('manufacturer.discounts.preview', $set) : null,
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
        <a href="{{ route('manufacturer.discounts.index') }}" class="text-sm text-gray-500 hover:text-[#c3242a]" @click="leave($event, '{{ route('manufacturer.discounts.index') }}')">← К списку наборов</a>
        @if($isEdit && $canManage)
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('manufacturer.discounts.copy', $set) }}">@csrf<button class="px-3 py-2 border border-gray-300 rounded-lg text-sm">Копировать набор</button></form>
                <form method="POST" action="{{ route('manufacturer.discounts.toggle', $set) }}">@csrf<button class="px-3 py-2 border border-gray-300 rounded-lg text-sm">{{ $set->effectiveStatus() === 'active' ? 'Отключить' : 'Включить' }}</button></form>
            </div>
        @endif
    </div>

    <form method="POST" action="{{ $isEdit ? route('manufacturer.discounts.update', $set) : route('manufacturer.discounts.store') }}" class="space-y-6" @submit="dirty = false" x-ref="discountForm">
        @csrf
        @if($isEdit) @method('PUT') @endif
        @if(session('conflict_warning'))
            <div class="bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded-lg text-sm space-y-3">
                <p>{{ session('conflict_warning') }}</p>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" name="confirm_conflicts" value="1" class="px-3 py-1.5 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800">Продолжить</button>
                    <a href="{{ $isEdit ? route('manufacturer.discounts.edit', $set) : route('manufacturer.discounts.create') }}" class="px-3 py-1.5 border border-amber-300 rounded-lg text-sm hover:bg-amber-100">Отмена</a>
                </div>
            </div>
        @endif
        <input type="hidden" name="audience_type" :value="type === 'individual' ? 'selected' : audienceType">
        <template x-for="id in selectedDistributors" :key="'dist-'+id">
            <input type="hidden" name="distributor_ids[]" :value="id">
        </template>

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
                <div class="md:col-span-2">
                    <input type="hidden" name="status" value="inactive">
                    <x-brand-checkbox
                        name="status"
                        value="active"
                        x-model="status"
                        true-value="active"
                        false-value="inactive"
                        :disabled="$readonly"
                    >Активен</x-brand-checkbox>
                </div>
            </div>
        </section>

        <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Тип скидки</h2>
            <x-styled-select name="type" x-model="type" :disabled="$readonly" wrapper-class="w-full md:w-1/2">
                @foreach($types as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-styled-select>
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
                <template x-for="(tier, index) in tiers" :key="index">
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-2 items-center">
                        <input type="number" step="0.01" :name="'tiers['+index+'][min_amount]'" x-model="tier.min_amount" placeholder="От суммы, ₽" @disabled($readonly) class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700">
                        <input type="number" :name="'tiers['+index+'][min_quantity]'" x-model="tier.min_quantity" placeholder="От кол-ва" @disabled($readonly) class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700">
                        <input type="number" step="0.01" :name="'tiers['+index+'][percent]'" x-model="tier.percent" placeholder="%" @disabled($readonly) class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700">
                        <input type="number" step="0.01" :name="'tiers['+index+'][amount]'" x-model="tier.amount" placeholder="Сумма, ₽" @disabled($readonly) class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700">
                        <button type="button" class="px-3 py-2 text-sm text-red-600 border border-red-200 rounded-lg hover:bg-red-50 disabled:opacity-50" @click="removeTier(index)" x-show="!readonly" :disabled="tiers.length <= 1">Удалить</button>
                    </div>
                </template>
                @if(! $readonly)
                    <button type="button" class="px-3 py-2 border border-gray-300 rounded-lg text-sm" @click="addTier()">Добавить ступень</button>
                @endif
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
            <x-brand-checkbox name="require_all_conditions" value="1" :checked="old('require_all_conditions', $set->require_all_conditions ?? true)" :disabled="$readonly">Применять только при выполнении всех условий</x-brand-checkbox>
            <x-brand-checkbox name="apply_only_active_products" value="1" :checked="old('apply_only_active_products', $set->apply_only_active_products ?? true)" :disabled="$readonly">Применять только к активным товарам</x-brand-checkbox>
            <x-brand-checkbox name="only_new_distributors" value="1" :checked="old('only_new_distributors', $set->only_new_distributors ?? false)" :disabled="$readonly">Только для новых дистрибьюторов</x-brand-checkbox>

            <div x-show="type !== 'category'" x-cloak>
                <x-brand-checkbox name="apply_to_all_products" value="1" x-model="applyAll" :disabled="$readonly" label-class="inline-flex items-center gap-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 cursor-pointer select-none">Применять ко всем товарам</x-brand-checkbox>
            </div>

            <div x-show="type === 'category' || !applyAll" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div :class="type === 'category' ? 'md:col-span-2' : ''" @click="if (!readonly) dirty = true">
                    <label class="mb-1.5 block text-sm font-medium">
                        Категории товаров
                        <span x-show="type === 'category'" class="text-red-500">*</span>
                    </label>
                    @if($readonly)
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
                            'selectedIds' => $selectedCategoryIds,
                            'placeholder' => 'Выберите категории',
                            'inputId' => 'discount-set-category-ids',
                            'allowClear' => true,
                            'clearLabel' => 'Сбросить категории',
                        ])
                    @endif
                    <p x-show="type === 'category'" x-cloak class="mt-1 text-xs text-gray-500">Скидка применится ко всем товарам выбранных категорий.</p>
                </div>
                <div x-show="type !== 'category'" x-cloak @click="if (!readonly) dirty = true">
                    <label class="mb-1.5 block text-sm font-medium">Исключения из категорий</label>
                    @if($readonly)
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            @forelse($excludedCategoryIds as $categoryId)
                                {{ $categoryNameById[$categoryId] ?? ('#'.$categoryId) }}@if(! $loop->last), @endif
                            @empty
                                Нет
                            @endforelse
                        </p>
                    @else
                        @include('manufacturer.products._category_tree_select', [
                            'name' => 'excluded_category_ids',
                            'multiple' => true,
                            'tree' => $categoryTree,
                            'categories' => $categories,
                            'selectedIds' => $excludedCategoryIds,
                            'placeholder' => 'Исключения',
                            'inputId' => 'discount-set-excluded-category-ids',
                            'allowClear' => true,
                            'clearLabel' => 'Сбросить исключения',
                        ])
                    @endif
                </div>
                <div class="md:col-span-2" x-show="type !== 'category'" x-cloak>
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
                <x-multi-select-filter name="region_ids[]" :options="$regionOptions->map(fn ($r) => ['value' => $r->id, 'label' => $r->name])" :selected="old('region_ids', $set?->intList($set->region_ids) ?? [])" placeholder="Все регионы" :searchable="true" :disabled="$readonly" />
            </div>
        </section>

        <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Привязка к дистрибьюторам</h2>
            <div x-show="type === 'individual'" x-cloak class="text-sm text-gray-600 dark:text-gray-300">
                Индивидуальная скидка привязывается только к одному партнёру.
            </div>
            <div x-show="type !== 'individual'" x-cloak>
                <x-styled-select x-model="audienceType" :disabled="$readonly" wrapper-class="w-full md:w-1/2">
                    @foreach($audienceTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-styled-select>
            </div>
            <div x-show="audienceType === 'company_types' && type !== 'individual'" x-cloak>
                <x-multi-select-filter name="company_types[]" :options="collect($companyTypes)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])" :selected="old('company_types', $set?->company_types ?? [])" placeholder="Типы компаний" :disabled="$readonly" />
            </div>
            <div x-show="audienceType === 'region' && type !== 'individual'" x-cloak>
                <label class="mb-1.5 block text-sm font-medium">Регионы дистрибьюторов</label>
                <x-multi-select-filter name="audience_region_ids[]" :options="$regionOptions->map(fn ($r) => ['value' => $r->id, 'label' => $r->name])" :selected="old('audience_region_ids', $set?->intList($set->audience_region_ids) ?? [])" placeholder="Регионы" :searchable="true" :disabled="$readonly" />
            </div>
            <div x-show="audienceType === 'selected' || type === 'individual'" x-cloak class="space-y-3">
                <div class="flex flex-wrap gap-2" x-show="!readonly">
                    <button type="button" class="px-3 py-2 bg-gray-900 text-white rounded-lg text-sm" @click="openDistributorModal()">Добавить дистрибьютора</button>
                </div>
                <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900/40 text-left text-gray-500">
                            <tr>
                                <th class="px-3 py-2 font-medium">Дистрибьютор</th>
                                <th class="px-3 py-2 font-medium">Регион</th>
                                <th class="px-3 py-2 font-medium">Статус</th>
                                <th class="px-3 py-2 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <template x-for="item in selectedDistributorRows" :key="item.id">
                                <tr>
                                    <td class="px-3 py-2" x-text="item.name"></td>
                                    <td class="px-3 py-2 text-gray-500" x-text="item.region || '—'"></td>
                                    <td class="px-3 py-2 text-gray-500" x-text="item.status === 'active' ? 'Активен' : 'Неактивен'"></td>
                                    <td class="px-3 py-2 text-right">
                                        <button type="button" class="text-red-600 hover:underline" @click="removeDistributor(item.id)" x-show="!readonly">Удалить привязку</button>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="selectedDistributorRows.length === 0">
                                <td colspan="4" class="px-3 py-4 text-gray-500">Дистрибьюторы не выбраны.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Временные ограничения</h2>
            <x-brand-checkbox name="is_indefinite" value="1" x-model="indefinite" :disabled="$readonly">Бессрочно</x-brand-checkbox>
            <div x-show="type === 'promo'" x-cloak>
                <x-brand-checkbox name="auto_deactivate" value="1" :checked="old('auto_deactivate', $set->auto_deactivate ?? true)" :disabled="$readonly">Автоматически отключать по окончании срока</x-brand-checkbox>
            </div>
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

        @if($isEdit && $canManage)
        <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Проверка и тестирование набора скидок</h2>
            <p class="text-sm text-gray-500">Выберите дистрибьютора и товар (поиск по названию или артикулу), укажите количество и при необходимости регион. Проверка использует текущие значения на форме, даже если набор ещё не сохранён.</p>
            <div class="grid grid-cols-1 md:grid-cols-[minmax(0,1.2fr)_minmax(0,1.8fr)_6.5rem_minmax(0,1fr)] gap-3 items-start">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Дистрибьютор</label>
                    <x-styled-select x-ref="previewDistributor">
                        <option value="">Не выбран</option>
                        @foreach($distributors as $distributor)
                            <option value="{{ $distributor['id'] }}">{{ $distributor['name'] }}</option>
                        @endforeach
                    </x-styled-select>
                </div>
                <div class="relative" @click.outside="previewProductOpen = false">
                    <label class="mb-1.5 block text-sm font-medium">Товар</label>
                    <div class="relative">
                        <input
                            type="search"
                            x-model="previewProductQuery"
                            @focus="openPreviewProductSearch()"
                            @input.debounce.300ms="onPreviewProductQueryInput()"
                            placeholder="Введите название или артикул"
                            autocomplete="off"
                            class="w-full pl-3 pr-10 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent"
                        >
                        <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <div
                        x-show="previewProductOpen"
                        x-cloak
                        class="absolute z-30 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg max-h-60 overflow-y-auto"
                    >
                        <template x-for="item in previewProductResults" :key="'preview-'+item.id">
                            <button
                                type="button"
                                class="block w-full text-left px-3 py-2.5 text-sm hover:bg-red-50 dark:hover:bg-red-900/20 border-b border-gray-50 dark:border-gray-700 last:border-0"
                                :class="previewProduct && Number(previewProduct.id) === Number(item.id) ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] font-medium' : 'text-gray-700 dark:text-gray-300'"
                                @click="selectPreviewProduct(item)"
                                x-text="item.label"
                            ></button>
                        </template>
                        <p class="px-3 py-3 text-sm text-gray-500" x-show="previewProductSearched && previewProductResults.length === 0">
                            Ничего не найдено. Попробуйте другое название или артикул.
                        </p>
                        <p class="px-3 py-3 text-sm text-gray-500" x-show="!previewProductSearched && previewProductResults.length === 0">
                            Начните вводить название или артикул товара.
                        </p>
                    </div>
                    <div class="mt-2" x-show="previewProduct" x-cloak>
                        <span class="inline-flex items-center gap-2 max-w-full px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-700 text-xs text-gray-700 dark:text-gray-200">
                            <span class="truncate" x-text="previewProduct?.label"></span>
                            <button type="button" class="shrink-0 text-gray-400 hover:text-gray-700" @click="clearPreviewProduct()" aria-label="Сбросить товар">×</button>
                        </span>
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Кол-во</label>
                    <input
                        type="number"
                        min="1"
                        step="1"
                        x-model.number="previewQty"
                        class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent"
                    >
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
            </div>
            <button type="button" @click="runPreview()" class="px-4 py-2 bg-gray-900 text-white rounded-lg text-sm" :disabled="previewLoading">Проверить скидку</button>
            <div x-show="preview" x-cloak class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 text-sm space-y-1">
                <p x-text="preview?.explanation"></p>
                <p x-show="preview?.applies">Конечная цена: <span class="font-semibold" x-text="formatMoney(preview?.unit_price)"></span> (было <span x-text="formatMoney(preview?.list_unit_price)"></span>)</p>
            </div>
        </section>
        @endif

        @if($isEdit)
        <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">История изменений</h2>
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
                <a href="{{ route('manufacturer.discounts.index') }}" @click="leave($event, '{{ route('manufacturer.discounts.index') }}')" class="px-4 py-2 border border-gray-300 rounded-lg text-sm">Отмена</a>
                @if($isEdit && $canDelete && ! $usedInOrders)
                    <button type="button" @click="showDeleteModal = true" class="px-4 py-2 border border-red-300 text-red-700 rounded-lg text-sm">Удалить набор</button>
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
    <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showDeleteModal = false">
        <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-xl p-6" @click.stop>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Удаление набора</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">Удалить набор «{{ $set->name }}»?</p>
            <form method="POST" action="{{ route('manufacturer.discounts.destroy', $set) }}" class="flex justify-end gap-3">
                @csrf
                @method('DELETE')
                <button type="button" @click="showDeleteModal = false" class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">Отмена</button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium">Удалить</button>
            </form>
        </div>
    </div>
    @endif

    <div x-show="showDistributorModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showDistributorModal = false">
        <div class="w-full max-w-2xl bg-white dark:bg-gray-800 rounded-xl shadow-xl p-6 space-y-4" @click.stop>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Добавить дистрибьютора</h3>
                    <p class="text-sm text-gray-500" x-text="type === 'individual' ? 'Выберите одного партнёра' : 'Отметьте одного или нескольких партнёров'"></p>
                </div>
                <button type="button" class="text-gray-400 hover:text-gray-600" @click="showDistributorModal = false">×</button>
            </div>
            <div class="flex flex-wrap gap-2">
                <input type="search" x-model="distributorQuery" placeholder="Поиск дистрибьютора" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent">
                <x-styled-select x-model="distributorRegion" size="sm" wrapper-class="min-w-[10rem]">
                    <option value="">Все регионы</option>
                    @foreach($regionOptions as $region)
                        <option value="{{ $region->name }}">{{ $region->name }}</option>
                    @endforeach
                </x-styled-select>
                <x-styled-select x-model="distributorStatus" size="sm" wrapper-class="min-w-[10rem]">
                    <option value="">Любой статус</option>
                    <option value="active">Активен</option>
                    <option value="blocked">Неактивен</option>
                </x-styled-select>
            </div>
            <div class="max-h-72 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg divide-y divide-gray-100 dark:divide-gray-700">
                <template x-for="item in filteredDistributors" :key="'modal-'+item.id">
                    <label class="flex items-center gap-2.5 px-3 py-2 text-sm cursor-pointer select-none">
                        <input
                            type="checkbox"
                            class="sr-only peer"
                            :checked="modalSelected.includes(String(item.id))"
                            @change="toggleModalDistributor(item.id)"
                        >
                        <x-brand-checkbox-mark />
                        <span x-text="item.name"></span>
                        <span class="text-xs text-gray-400" x-text="item.region || ''"></span>
                    </label>
                </template>
                <p class="px-3 py-4 text-sm text-gray-500" x-show="filteredDistributors.length === 0">Никого не найдено.</p>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="px-4 py-2 text-gray-600" @click="showDistributorModal = false">Отмена</button>
                <button type="button" class="px-4 py-2 bg-[#c3242a] text-white rounded-lg text-sm" @click="confirmDistributorModal()">Добавить</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/ru.js"></script>
<script>
function discountSetForm(config) {
    return {
        ...config,
        init() {
            if (this.type === 'category') {
                this.applyAll = false;
            }
            if (this.type === 'individual') {
                this.audienceType = 'selected';
                if (this.selectedDistributors.length > 1) {
                    this.selectedDistributors = this.selectedDistributors.slice(0, 1);
                }
            }
            this.$watch('type', (value) => {
                if (value === 'category') {
                    this.applyAll = false;
                }
                if (value === 'individual') {
                    this.audienceType = 'selected';
                    if (this.selectedDistributors.length > 1) {
                        this.selectedDistributors = this.selectedDistributors.slice(0, 1);
                    }
                }
            });
        },
        get filteredDistributors() {
            const q = (this.distributorQuery || '').toLowerCase();
            return (this.distributors || []).filter((item) => {
                if (q && !(item.name || '').toLowerCase().includes(q)) return false;
                if (this.distributorRegion && item.region !== this.distributorRegion) return false;
                if (this.distributorStatus && item.status !== this.distributorStatus) return false;
                return true;
            });
        },
        get selectedDistributorRows() {
            const map = Object.fromEntries((this.distributors || []).map((item) => [String(item.id), item]));
            return (this.selectedDistributors || []).map((id) => {
                const row = map[String(id)];
                return row || { id, name: '#' + id, region: null, status: '' };
            });
        },
        addTier() {
            this.tiers = [...this.tiers, { min_amount: '', min_quantity: '', percent: '', amount: '' }];
            this.dirty = true;
        },
        removeTier(index) {
            if (this.tiers.length <= 1) return;
            this.tiers = this.tiers.filter((_, i) => i !== index);
            this.dirty = true;
        },
        async searchProducts() {
            const q = this.productQuery.trim();
            if (q.length < 1) { this.productResults = []; return; }
            const url = this.searchUrl + '?q=' + encodeURIComponent(q);
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            this.productResults = await res.json();
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
            const url = this.searchUrl + '?q=' + encodeURIComponent(q);
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
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
        openDistributorModal() {
            this.modalSelected = [...this.selectedDistributors];
            this.distributorQuery = '';
            this.distributorRegion = '';
            this.distributorStatus = '';
            this.showDistributorModal = true;
        },
        toggleModalDistributor(id) {
            id = String(id);
            if (this.type === 'individual') {
                this.modalSelected = this.modalSelected.includes(id) ? [] : [id];
                return;
            }
            if (this.modalSelected.includes(id)) {
                this.modalSelected = this.modalSelected.filter(v => v !== id);
            } else {
                this.modalSelected = [...this.modalSelected, id];
            }
        },
        confirmDistributorModal() {
            if (this.type === 'individual') {
                this.selectedDistributors = this.modalSelected.slice(0, 1);
            } else {
                this.selectedDistributors = [...this.modalSelected];
            }
            this.showDistributorModal = false;
            this.dirty = true;
        },
        removeDistributor(id) {
            id = String(id);
            this.selectedDistributors = this.selectedDistributors.filter(v => v !== id);
            this.dirty = true;
        },
        async runPreview() {
            if (!this.previewUrl) return;
            const selectValue = (ref) => ref?.querySelector?.('select')?.value ?? ref?.value ?? '';
            const distributorId = selectValue(this.$refs.previewDistributor);
            if (!distributorId) {
                this.preview = { applies: false, explanation: 'Выберите дистрибьютора для проверки.' };
                return;
            }
            if (!this.previewProduct?.id) {
                this.preview = { applies: false, explanation: 'Найдите и выберите товар: введите название или артикул в поле «Товар».' };
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
            body.set('distributor_id', distributorId);
            body.set('product_id', String(this.previewProduct.id));
            body.set('quantity', String(Math.max(1, Number(this.previewQty) || 1)));
            const regionId = selectValue(this.$refs.previewRegion);
            if (regionId) {
                body.set('region_id', regionId);
            } else {
                body.delete('region_id');
            }
            // Alpine-поля и чекбоксы — явно, иначе FormData может не совпасть с тем, что видно на экране
            body.set('audience_type', this.type === 'individual' ? 'selected' : this.audienceType);
            body.set('type', this.type);
            body.set('apply_to_all_products', this.applyAll ? '1' : '0');
            body.set('is_indefinite', this.indefinite ? '1' : '0');
            const checkboxNames = ['require_all_conditions', 'apply_only_active_products', 'only_new_distributors', 'auto_deactivate'];
            checkboxNames.forEach((name) => {
                const checked = Boolean(form?.querySelector(`input[name="${name}"]:checked`));
                body.set(name, checked ? '1' : '0');
            });
            if (this.applyAll && this.type !== 'category') {
                ['product_ids[]', 'product_ids', 'category_ids[]', 'category_ids'].forEach((key) => body.delete(key));
            }
            if (this.type === 'individual' || this.audienceType === 'selected') {
                body.delete('distributor_ids[]');
                body.delete('distributor_ids');
                this.selectedDistributors.forEach((id) => body.append('distributor_ids[]', id));
            }
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
