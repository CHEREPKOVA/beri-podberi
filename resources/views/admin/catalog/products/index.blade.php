@extends('layouts.app')

@section('title', 'Товары каталога')
@section('heading', 'Управление товарами')

@section('content')
<div class="space-y-6">
    @include('admin.partials.flash')
    <a href="{{ route('admin.catalog.index') }}" class="text-sm text-gray-500 hover:text-[#c3242a]">← Каталог</a>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            @php
                $selectedCategoryIds = request()->input('category_ids', []);
                if (! is_array($selectedCategoryIds)) {
                    $selectedCategoryIds = [$selectedCategoryIds];
                }
                $selectedCategoryIds = array_values(array_map('intval', array_filter($selectedCategoryIds)));
            @endphp
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Поиск по названию или SKU" class="w-full sm:w-72 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm" />
                <div class="min-w-[11rem] max-w-[16rem]">
                    @include('admin.partials.category_tree_select', [
                        'name' => 'category_ids',
                        'categoryTree' => $categoryTree,
                        'categories' => $categories,
                        'selectedIds' => $selectedCategoryIds,
                        'multiple' => true,
                        'placeholder' => 'Все категории',
                        'clearLabel' => 'Все категории',
                        'inputId' => 'admin-catalog-products-index-category-filter',
                        'buttonClass' => 'w-full flex items-center justify-between gap-2 pl-3 pr-8 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-left text-sm text-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-[#c3242a] focus:border-transparent cursor-pointer',
                    ])
                </div>
                <button type="submit" class="shrink-0 px-4 py-2 bg-[#c3242a] text-white rounded-lg text-sm hover:bg-[#a01e24]">Применить</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Товар</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Производитель</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Категория</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Статус</th>
                        <th class="w-28 px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($products as $product)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 group">
                        <td class="px-4 py-3 text-sm">
                            <a href="{{ route('admin.catalog.products.show', $product) }}" class="font-medium text-gray-900 dark:text-white hover:text-[#c3242a] dark:hover:text-red-400 transition-colors">
                                {{ $product->name }}
                            </a>
                            <span class="text-gray-500 dark:text-gray-400">({{ $product->sku }})</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $product->manufacturerProfile?->company_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $product->category?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $product->statusLabel() }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <a href="{{ route('admin.catalog.products.show', $product) }}"
                                   class="p-1.5 text-gray-400 hover:text-[#c3242a] rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600"
                                   title="Просмотр">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                <a href="{{ route('admin.catalog.products.edit', $product) }}"
                                   class="p-1.5 text-gray-400 hover:text-[#c3242a] rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600"
                                   title="Изменить">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Товары не найдены.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">{{ $products->links() }}</div>
    </div>
</div>
@endsection
