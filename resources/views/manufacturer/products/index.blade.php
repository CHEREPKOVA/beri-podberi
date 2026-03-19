@extends('layouts.app')

@section('title', 'Номенклатура')
@section('heading', 'Номенклатура')

@section('content')
<div x-data="{
    selectedProducts: [],
    selectAll: false,
    showBulkModal: false,
    bulkAction: '',
    showDeleteModal: false,
    deleteFormAction: '',
    deleteMessage: '',
    toggleAll() {
        if (this.selectAll) {
            this.selectedProducts = [...document.querySelectorAll('[data-product-id]')].map(el => el.dataset.productId);
        } else {
            this.selectedProducts = [];
        }
    }
}" class="space-y-6">

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

    @if(session('warning'))
    <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded-lg">
        {{ session('warning') }}
        @if(session('import_errors'))
        <ul class="mt-2 text-sm list-disc list-inside">
            @foreach(session('import_errors') as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
        @endif
    </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <form method="GET" class="flex flex-wrap items-center gap-3">
                    <div class="relative">
                        <input type="text" name="search" value="{{ request('search') }}"
                            placeholder="Поиск по артикулу или названию..."
                            class="w-64 pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
                        <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>

                    <div class="relative">
                        <select name="category" class="appearance-none pl-3 pr-8 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent cursor-pointer">
                            <option value="">Все категории</option>
                            @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ request('category') == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                            @endforeach
                        </select>
                        <svg class="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>

                    <div class="relative">
                        <select name="status" class="appearance-none pl-3 pr-8 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent cursor-pointer">
                            <option value="">Любой статус</option>
                            @foreach(\App\Models\Product::statusLabels() as $key => $label)
                            <option value="{{ $key }}" {{ request('status') == $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <svg class="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>

                    <div class="relative">
                        <select name="has_stock" class="appearance-none pl-3 pr-8 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent cursor-pointer">
                            <option value="">Наличие</option>
                            <option value="yes" {{ request('has_stock') === 'yes' ? 'selected' : '' }}>В наличии</option>
                            <option value="no" {{ request('has_stock') === 'no' ? 'selected' : '' }}>Нет в наличии</option>
                        </select>
                        <svg class="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>

                    <div x-data="{ checked: {{ request('needs_update') ? 'true' : 'false' }} }">
                        <label class="flex cursor-pointer items-center text-sm font-medium text-gray-700 dark:text-gray-400 select-none">
                            <div class="relative">
                                <input type="checkbox" name="needs_update" value="1" class="sr-only" x-model="checked">
                                <div :class="checked ? 'border-[#c3242a] bg-[#c3242a]' : 'bg-transparent border-gray-300 dark:border-gray-600'"
                                    class="mr-2.5 flex h-5 w-5 items-center justify-center rounded-md border-[1.25px] transition-colors hover:border-[#c3242a] dark:hover:border-[#c3242a]">
                                    <span :class="checked ? 'opacity-100' : 'opacity-0'" class="transition-opacity">
                                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M11.6666 3.5L5.24992 9.91667L2.33325 7" stroke="white" stroke-width="1.94437" stroke-linecap="round" stroke-linejoin="round"></path>
                                        </svg>
                                    </span>
                                </div>
                            </div>
                            Требуют обновления
                        </label>
                    </div>

                    <button type="submit" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 text-sm font-medium">
                        Применить
                    </button>
                    @if(request()->hasAny(['search', 'category', 'status', 'has_stock', 'needs_update']))
                    <a href="{{ route('manufacturer.products.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Сбросить</a>
                    @endif
                </form>

                <div class="flex items-center gap-2">
                    <a href="{{ route('manufacturer.products.create') }}"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-[#c3242a] text-white rounded-lg hover:bg-[#a01e24] text-sm font-medium transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Добавить товар
                    </a>

                    <a href="{{ route('manufacturer.products.import') }}"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 text-sm font-medium transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        Импорт
                    </a>

                    <a href="{{ route('manufacturer.products.export', request()->query()) }}"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 text-sm font-medium transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Экспорт
                    </a>
                </div>
            </div>
        </div>

        <div x-show="selectedProducts.length > 0" x-cloak class="p-4 bg-blue-50 dark:bg-blue-900/20 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <span class="text-sm text-blue-700 dark:text-blue-300">
                    Выбрано товаров: <strong x-text="selectedProducts.length"></strong>
                </span>
                <div class="flex items-center gap-2">
                    <div class="relative">
                        <select x-model="bulkAction" class="appearance-none pl-3 pr-8 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                            <option value="">Действие...</option>
                            <option value="publish">Опубликовать</option>
                            <option value="hide">Скрыть</option>
                            <option value="change_category">Изменить категорию</option>
                            <option value="change_price">Установить цену</option>
                            <option value="apply_discount">Применить скидку</option>
                            <option value="update_stock">Обновить остаток</option>
                            <option value="delete">Удалить</option>
                        </select>
                        <svg class="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                    <button @click="showBulkModal = true" :disabled="!bulkAction"
                        class="px-4 py-1.5 bg-[#c3242a] text-white rounded-lg text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                        Применить
                    </button>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="w-12 px-4 py-3">
                            <input type="checkbox" x-model="selectAll" @change="toggleAll()"
                                class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]" />
                        </th>
                        <th class="w-16 px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Фото</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'sku', 'dir' => request('sort') === 'sku' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" class="hover:text-gray-700">
                                Артикул
                                @if(request('sort') === 'sku')
                                <span class="ml-1">{{ request('dir') === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </a>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'name', 'dir' => request('sort') === 'name' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" class="hover:text-gray-700">
                                Наименование
                            </a>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Категория</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'base_price', 'dir' => request('sort') === 'base_price' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" class="hover:text-gray-700">
                                Цена
                            </a>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Остаток</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Статус</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'updated_at', 'dir' => request('sort') === 'updated_at' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" class="hover:text-gray-700">
                                Обновлён
                            </a>
                        </th>
                        <th class="w-24 px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($products as $product)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 group" data-product-id="{{ $product->id }}">
                        <td class="px-4 py-3">
                            <input type="checkbox" value="{{ $product->id }}" x-model="selectedProducts"
                                class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]" />
                        </td>
                        <td class="px-4 py-3">
                            @if($product->primaryImage())
                            <img src="{{ $product->primaryImage()->url }}" alt="{{ $product->name }}"
                                class="w-12 h-12 object-cover rounded-lg" />
                            @else
                            <div class="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-sm font-mono text-gray-600 dark:text-gray-400">{{ $product->sku }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('manufacturer.products.edit', $product) }}" class="text-sm font-medium text-gray-900 dark:text-white hover:text-[#c3242a]">
                                {{ $product->name }}
                            </a>
                            <div class="flex items-center gap-2 mt-1">
                                @if(!$product->hasStock())
                                <span class="inline-flex items-center text-xs text-orange-600 bg-orange-50 px-2 py-0.5 rounded">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                    Нет остатков
                                </span>
                                @endif
                                @if($product->isSynced())
                                <span class="inline-flex items-center text-xs text-blue-600 bg-blue-50 px-2 py-0.5 rounded" title="Обновлено {{ $product->synced_at?->format('d.m.Y H:i') }}">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    Авто ({{ $product->sync_source }})
                                </span>
                                @endif
                                @if($product->is_modified)
                                <span class="inline-flex items-center text-xs text-yellow-600 bg-yellow-50 px-2 py-0.5 rounded">
                                    Изменён
                                </span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                            {{ $product->category?->name ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">
                            {{ $product->base_price ? number_format($product->base_price, 2, ',', ' ') . ' ₽' : '—' }}
                        </td>
                        <td class="px-4 py-3 text-sm {{ $product->hasStock() ? 'text-green-600' : 'text-red-600' }}">
                            {{ $product->total_stock }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $product->statusBadgeClass() }}">
                                {{ $product->statusLabel() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">
                            {{ $product->updated_at->format('d.m.Y') }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <a href="{{ route('manufacturer.products.edit', $product) }}" class="p-1.5 text-gray-400 hover:text-[#c3242a] rounded-lg hover:bg-gray-100" title="Редактировать">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </a>
                                <button type="button"
                                    @click="deleteFormAction = '{{ route('manufacturer.products.destroy', $product) }}'; deleteMessage = {{ json_encode('Удалить товар «' . $product->name . '»? Это действие нельзя отменить.') }}; showDeleteModal = true"
                                    class="p-1.5 text-gray-400 hover:text-red-600 rounded-lg hover:bg-gray-100"
                                    title="Удалить">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="px-4 py-12 text-center">
                            <div class="flex flex-col items-center">
                                <svg class="w-12 h-12 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                </svg>
                                <p class="text-gray-500 mb-4">Товары не найдены</p>
                                <a href="{{ route('manufacturer.products.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-[#c3242a] text-white rounded-lg hover:bg-[#a01e24] text-sm font-medium">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Добавить первый товар
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($products->hasPages())
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $products->links() }}
        </div>
        @endif
    </div>

    <div x-show="showBulkModal" x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showBulkModal = false">
        <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-xl p-6">
            <h3 class="text-lg font-semibold mb-4">Групповое действие</h3>

            <form method="POST" action="{{ route('manufacturer.products.bulk') }}">
                @csrf
                <template x-for="id in selectedProducts" :key="id">
                    <input type="hidden" name="product_ids[]" :value="id" />
                </template>
                <input type="hidden" name="action" x-model="bulkAction" />

                <template x-if="bulkAction === 'change_category'">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Новая категория</label>
                        <div class="relative">
                            <select name="category_id" class="w-full appearance-none pl-3 pr-10 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm cursor-pointer">
                                @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                            <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>
                </template>

                <template x-if="bulkAction === 'change_price'">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Новая цена (₽)</label>
                        <input type="number" name="price" step="0.01" min="0" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
                    </div>
                </template>

                <template x-if="bulkAction === 'apply_discount'">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Скидка (%)</label>
                        <input type="number" name="discount_percent" min="0" max="100" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
                    </div>
                </template>

                <template x-if="bulkAction === 'update_stock'">
                    <div class="mb-4 space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Склад</label>
                            <div class="relative">
                                <select name="warehouse_id" class="w-full appearance-none pl-3 pr-10 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm cursor-pointer">
                                    @foreach(auth()->user()->manufacturerProfile?->warehouses ?? [] as $warehouse)
                                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                    @endforeach
                                </select>
                                <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Количество</label>
                            <input type="number" name="stock_quantity" min="0" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
                        </div>
                    </div>
                </template>

                <template x-if="bulkAction === 'delete'">
                    <p class="text-sm text-red-600 mb-4">
                        Вы уверены, что хотите удалить выбранные товары? Это действие нельзя отменить.
                    </p>
                </template>

                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" @click="showBulkModal = false"
                        class="px-4 py-2 text-gray-600 hover:text-gray-800">
                        Отмена
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-[#c3242a] text-white rounded-lg hover:bg-[#a01e24] font-medium">
                        Подтвердить
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Модальное окно подтверждения удаления одного товара --}}
    <div x-show="showDeleteModal" x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showDeleteModal = false">
        <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-xl p-6" @click.stop>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Удаление товара</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-6" x-text="deleteMessage"></p>
            <form :action="deleteFormAction" method="POST" class="flex justify-end gap-3">
                @csrf
                @method('DELETE')
                <button type="button" @click="showDeleteModal = false"
                    class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                    Отмена
                </button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium">
                    Удалить
                </button>
            </form>
        </div>
    </div>
</div>

@push('styles')
<style>
    [x-cloak] { display: none !important; }
</style>
@endpush
@endsection
