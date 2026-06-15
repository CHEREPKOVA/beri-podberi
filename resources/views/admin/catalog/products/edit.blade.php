@extends('layouts.app')

@section('title', 'Редактирование товара')
@section('heading', 'Редактирование товара')

@section('content')
@php
    $initialCategoryId = old('category_id', $product->category_id);
    $initialCategoryId = $initialCategoryId !== null && $initialCategoryId !== '' ? (int) $initialCategoryId : null;
@endphp
<div x-data="{
    activeTab: '{{ $tab }}',
    unsavedChanges: false,
    savedCategoryId: @js($initialCategoryId),
    currentCategoryId: @js($initialCategoryId),
    categoryPendingSave: false,
    normalizeCategoryId(value) {
        return value === null || value === undefined || value === '' ? '' : String(value);
    },
    syncCategoryPendingSave() {
        this.categoryPendingSave = this.normalizeCategoryId(this.savedCategoryId) !== this.normalizeCategoryId(this.currentCategoryId);
    },
    onMainCategoryChanged(event) {
        this.currentCategoryId = event.detail?.categoryId ?? null;
        this.syncCategoryPendingSave();
        this.unsavedChanges = true;
    },
    init() {
        this.syncCategoryPendingSave();
        this.$watch('unsavedChanges', (value) => {
            if (value) {
                window.onbeforeunload = () => 'Изменения не сохранены. Сохранить перед выходом?';
            } else {
                window.onbeforeunload = null;
            }
        });
    }
}" @input="unsavedChanges = true" @product-main-category-changed.window="onMainCategoryChanged($event)" class="space-y-6">

    @include('admin.partials.flash')

    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.catalog.products.show', $product) }}"
           class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-[#c3242a] dark:hover:text-red-400 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Назад к карточке
        </a>
        <div class="flex items-center gap-3 text-sm">
            <a href="{{ route('admin.catalog.analogs.edit', $product) }}" class="text-gray-500 hover:text-[#c3242a] dark:hover:text-red-400">
                Управление аналогами
            </a>
            <a href="{{ route('admin.catalog.products.index') }}" class="text-gray-500 hover:text-[#c3242a] dark:hover:text-red-400">
                К списку товаров
            </a>
        </div>
    </div>

    @if(!empty($qualityIssues))
    <div class="bg-amber-50 border border-amber-200 text-amber-900 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-200 px-4 py-3 rounded-lg">
        <p class="font-medium mb-1">Замечания по качеству карточки</p>
        <ul class="text-sm list-disc list-inside">
            @foreach($qualityIssues as $issue)
            <li>{{ $issue }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form id="admin-product-edit-form"
          action="{{ route('admin.catalog.products.update', $product) }}"
          method="POST"
          enctype="multipart/form-data"
          @submit="unsavedChanges = false">
        @csrf
        @method('PUT')
        <input type="hidden" name="tab" x-model="activeTab" />

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="flex flex-wrap -mb-px">
                    @php
                        $tabs = [
                            'basic' => 'Основная информация',
                            'attributes' => 'Характеристики',
                            'additional' => 'Дополнительно',
                            'publication' => 'Публикация',
                        ];
                    @endphp
                    @foreach($tabs as $key => $label)
                    <button type="button"
                        @click="activeTab = '{{ $key }}'"
                        :class="activeTab === '{{ $key }}'
                            ? 'border-[#c3242a] text-[#c3242a]'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:hover:text-gray-300'"
                        class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-colors inline-flex items-center gap-1.5">
                        <span>{{ $label }}</span>
                        @if($key === 'attributes')
                        <span
                            x-show="categoryPendingSave"
                            x-cloak
                            class="inline-flex items-center justify-center min-w-[1.125rem] h-[1.125rem] px-1 rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 text-[10px] font-bold leading-none"
                            title="Категория изменена — сохраните товар, чтобы обновить список характеристик"
                        >!</span>
                        @endif
                    </button>
                    @endforeach
                </nav>
            </div>

            <div class="px-6 pt-4">
                <div class="flex gap-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-200">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p>Администратор управляет содержимым карточки: описание, категории, характеристики, изображения и публикацию. <strong>Цены и остатки</strong> редактируются только в кабинете производителя.</p>
                </div>
                @include('admin.partials.form-errors')
            </div>

            <div class="p-6">
                <div x-show="activeTab === 'basic'" x-cloak>
                    @include('manufacturer.products._tab_basic', [
                        'product' => $product,
                        'categoryTree' => $categoryTree,
                        'categories' => $categories,
                        'unitTypes' => $unitTypes,
                        'skuReadonly' => true,
                        'mediaRoutes' => $mediaRoutes,
                    ])
                </div>

                <div x-show="activeTab === 'attributes'" x-cloak>
                    @include('manufacturer.products._tab_attributes', ['product' => $product, 'attributes' => $attributes])
                </div>

                <div x-show="activeTab === 'additional'" x-cloak>
                    @include('manufacturer.products._tab_additional', ['product' => $product, 'mediaRoutes' => $mediaRoutes])
                </div>

                <div x-show="activeTab === 'publication'" x-cloak>
                    @include('admin.catalog.products._tab_publication', [
                        'product' => $product,
                        'regions' => $regions,
                        'qualityIssues' => $qualityIssues,
                    ])
                </div>
            </div>

            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/50 border-t border-gray-200 dark:border-gray-700 rounded-b-xl flex flex-wrap items-center justify-between gap-4">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    <span>Создан: {{ $product->created_at->format('d.m.Y H:i') }}</span>
                    <span class="mx-2">|</span>
                    <span>Обновлён: {{ $product->updated_at->format('d.m.Y H:i') }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.catalog.products.show', $product) }}" class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                        Отмена
                    </a>
                    <button type="submit" class="px-6 py-2 bg-[#c3242a] text-white rounded-lg hover:bg-[#a01e24] font-medium transition-colors">
                        Сохранить
                    </button>
                </div>
            </div>
        </div>
    </form>

    @stack('product-external-forms')

    <div x-data="{ auxDelete: { show: false, action: '', message: '' } }"
        @open-aux-delete.window="auxDelete = { show: true, action: $event.detail.action, message: $event.detail.message }">
        <div x-show="auxDelete.show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @click.self="auxDelete.show = false">
            <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-xl p-6" @click.stop>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Подтверждение</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6" x-text="auxDelete.message"></p>
                <form :action="auxDelete.action" method="POST" class="flex justify-end gap-3">
                    @csrf
                    @method('DELETE')
                    <button type="button" @click="auxDelete.show = false"
                        class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">Отмена</button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium">Удалить</button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    [x-cloak] { display: none !important; }
</style>
@endpush
@endsection
