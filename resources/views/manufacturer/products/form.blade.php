@extends('layouts.app')

@section('title', $product ? 'Редактирование товара' : 'Новый товар')
@section('heading', $product ? 'Редактирование товара' : 'Новый товар')

@section('content')
@php
    $initialCategoryId = old('category_id', $product?->category_id);
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

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
        {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
        {{ session('error') }}
    </div>
    @endif

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
        <ul class="list-disc list-inside">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="flex items-center justify-between">
        <a href="{{ route('manufacturer.products.index') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">
            <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Назад к списку
        </a>

        @if($product)
        <div class="flex items-center gap-2">
            @if($product->status !== 'active')
            <form action="{{ route('manufacturer.products.publish', $product) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm font-medium">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Опубликовать
                </button>
            </form>
            @else
            <form action="{{ route('manufacturer.products.hide', $product) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 text-sm font-medium">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                    </svg>
                    Скрыть
                </button>
            </form>
            @endif
        </div>
        @endif
    </div>

    <form id="product-edit-form" action="{{ $product ? route('manufacturer.products.update', $product) : route('manufacturer.products.store') }}"
        method="POST" enctype="multipart/form-data" @submit="unsavedChanges = false">
        @csrf
        @if($product)
        @method('PUT')
        @endif
        <input type="hidden" name="tab" x-model="activeTab" />

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="flex flex-wrap -mb-px">
                    @php
                        $tabs = [
                            'basic' => 'Основная информация',
                            'prices' => 'Цены и остатки',
                            'attributes' => 'Характеристики',
                            'analogs' => 'Аналоги',
                            'additional' => 'Дополнительно',
                            'publication' => 'Публикация и доступность',
                        ];
                    @endphp
                    @foreach($tabs as $key => $label)
                    <button type="button"
                        @click="activeTab = '{{ $key }}'"
                        :class="activeTab === '{{ $key }}'
                            ? 'border-[#c3242a] text-[#c3242a]'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
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

            <div class="p-6">
                {{-- Вкладка: Основная информация --}}
                <div x-show="activeTab === 'basic'" x-cloak>
                    @include('manufacturer.products._tab_basic', ['product' => $product, 'categoryTree' => $categoryTree, 'categories' => $categories, 'unitTypes' => $unitTypes])
                </div>

                {{-- Вкладка: Цены и остатки --}}
                <div x-show="activeTab === 'prices'" x-cloak>
                    @include('manufacturer.products._tab_prices', ['product' => $product, 'warehouses' => $warehouses, 'regions' => $regions])
                </div>

                {{-- Вкладка: Характеристики --}}
                <div x-show="activeTab === 'attributes'" x-cloak>
                    @include('manufacturer.products._tab_attributes', ['product' => $product, 'attributes' => $attributes])
                </div>

                {{-- Вкладка: Аналоги --}}
                <div x-show="activeTab === 'analogs'" x-cloak>
                    @include('manufacturer.products._tab_analogs', ['product' => $product, 'selectedAnalogs' => $selectedAnalogs ?? collect()])
                </div>

                {{-- Вкладка: Дополнительно --}}
                <div x-show="activeTab === 'additional'" x-cloak>
                    @include('manufacturer.products._tab_additional', ['product' => $product])
                </div>

                {{-- Вкладка: Публикация --}}
                <div x-show="activeTab === 'publication'" x-cloak>
                    @include('manufacturer.products._tab_publication', ['product' => $product, 'regions' => $regions])
                </div>
            </div>

            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/50 border-t border-gray-200 dark:border-gray-700 rounded-b-xl flex items-center justify-between">
                <div class="text-sm text-gray-500">
                    @if($product)
                    <span>Создан: {{ $product->created_at->format('d.m.Y H:i') }}</span>
                    <span class="mx-2">|</span>
                    <span>Обновлён: {{ $product->updated_at->format('d.m.Y H:i') }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('manufacturer.products.index') }}" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                        Отмена
                    </a>
                    <button type="submit" class="px-6 py-2 bg-[#c3242a] text-white rounded-lg hover:bg-[#a01e24] font-medium">
                        Сохранить
                    </button>
                </div>
            </div>
        </div>
    </form>

    @stack('product-external-forms')

    {{-- Вспомогательные DELETE-формы только снаружи основной формы (вложенные <form> ломали «Сохранить») --}}
    @if($product)
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
    @endif
</div>

@push('styles')
<style>
    [x-cloak] { display: none !important; }
</style>
@endpush
@endsection
