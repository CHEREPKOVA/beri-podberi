@extends('layouts.app')

@section('title', 'Контроль качества каталога')
@section('heading', 'Контроль качества данных')

@section('content')
<div class="space-y-6">
    @include('admin.partials.flash')

    <a href="{{ route('admin.catalog.index') }}" class="text-sm text-gray-500 hover:text-[#c3242a]">← Каталог</a>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 font-medium">Товары без категории</div>
            <div class="p-4 space-y-3">
                @forelse($withoutCategory as $product)
                <a href="{{ route('admin.catalog.products.edit', $product) }}" class="block text-sm hover:text-[#c3242a]">
                    {{ $product->name }} <span class="text-gray-500">({{ $product->sku }})</span>
                </a>
                @empty
                <p class="text-sm text-gray-500">Проблем не найдено.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 font-medium">Товары без характеристик</div>
            <div class="p-4 space-y-3">
                @forelse($withoutAttributes as $product)
                <a href="{{ route('admin.catalog.products.edit', $product) }}" class="block text-sm hover:text-[#c3242a]">
                    {{ $product->name }} <span class="text-gray-500">({{ $product->sku }})</span>
                </a>
                @empty
                <p class="text-sm text-gray-500">Проблем не найдено.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 font-medium">Товары без изображений</div>
            <div class="p-4 space-y-3">
                @forelse($withoutImages as $product)
                <a href="{{ route('admin.catalog.products.edit', $product) }}" class="block text-sm hover:text-[#c3242a]">
                    {{ $product->name }} <span class="text-gray-500">({{ $product->sku }})</span>
                </a>
                @empty
                <p class="text-sm text-gray-500">Проблем не найдено.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
