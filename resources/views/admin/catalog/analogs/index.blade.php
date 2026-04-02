@extends('layouts.app')

@section('title', 'Аналоги товаров')
@section('heading', 'Управление аналогами')

@section('content')
<div class="space-y-6">
    @include('admin.partials.flash')
    <a href="{{ route('admin.catalog.index') }}" class="text-sm text-gray-500 hover:text-[#c3242a]">← Каталог</a>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <form method="GET" class="flex items-center gap-3">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Поиск по названию или SKU" class="w-72 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm" />
                <button class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm">Найти</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Товар</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Категория</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Связей аналогов</th>
                        <th class="w-24 px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($products as $product)
                    <tr>
                        <td class="px-4 py-3 text-sm">{{ $product->name }} <span class="text-gray-500">({{ $product->sku }})</span></td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $product->category?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $product->analogs_count }}</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('admin.catalog.analogs.edit', $product) }}" class="text-sm text-[#c3242a] hover:underline">Управлять</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">Товары не найдены.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">{{ $products->links() }}</div>
    </div>
</div>
@endsection
