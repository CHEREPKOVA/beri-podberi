@extends('layouts.app')

@section('title', 'Контроль качества каталога')
@section('heading', 'Контроль качества данных')

@section('content')
<div class="space-y-6">
    @include('admin.partials.flash')

    <a href="{{ route('admin.catalog.index') }}" class="text-sm text-gray-500 hover:text-[#c3242a]">← Каталог</a>

    <p class="text-sm text-gray-600 dark:text-gray-400 max-w-4xl">
        Выявление проблемных карточек: отсутствие категории, незаполненные характеристики, изображения и возможные дубликаты.
        Переход ведёт сразу на нужную вкладку редактирования.
    </p>

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 font-medium">Товары без категории</div>
            <div class="p-4 space-y-3">
                @forelse($withoutCategory as $product)
                <a href="{{ route('admin.catalog.products.edit', ['product' => $product, 'tab' => 'basic']) }}" class="block text-sm hover:text-[#c3242a]">
                    {{ $product->name }} <span class="text-gray-500">({{ $product->sku }})</span>
                </a>
                @empty
                <p class="text-sm text-gray-500">Проблем не найдено.</p>
                @endforelse
            </div>
            @if($withoutCategory->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">{{ $withoutCategory->links() }}</div>
            @endif
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 font-medium">Без обязательных характеристик категории</div>
            <div class="p-4 space-y-3">
                @forelse($missingRequiredAttributes as $product)
                <a href="{{ route('admin.catalog.products.edit', ['product' => $product, 'tab' => 'attributes']) }}" class="block text-sm hover:text-[#c3242a]">
                    {{ $product->name }} <span class="text-gray-500">({{ $product->sku }})</span>
                </a>
                @empty
                <p class="text-sm text-gray-500">Проблем не найдено.</p>
                @endforelse
            </div>
            @if($missingRequiredAttributes->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">{{ $missingRequiredAttributes->links() }}</div>
            @endif
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 font-medium">Товары без любых характеристик</div>
            <div class="p-4 space-y-3">
                @forelse($withoutAttributes as $product)
                <a href="{{ route('admin.catalog.products.edit', ['product' => $product, 'tab' => 'attributes']) }}" class="block text-sm hover:text-[#c3242a]">
                    {{ $product->name }} <span class="text-gray-500">({{ $product->sku }})</span>
                </a>
                @empty
                <p class="text-sm text-gray-500">Проблем не найдено.</p>
                @endforelse
            </div>
            @if($withoutAttributes->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">{{ $withoutAttributes->links() }}</div>
            @endif
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 font-medium">Товары без изображений</div>
            <div class="p-4 space-y-3">
                @forelse($withoutImages as $product)
                <a href="{{ route('admin.catalog.products.edit', ['product' => $product, 'tab' => 'basic']) }}" class="block text-sm hover:text-[#c3242a]">
                    {{ $product->name }} <span class="text-gray-500">({{ $product->sku }})</span>
                </a>
                @empty
                <p class="text-sm text-gray-500">Проблем не найдено.</p>
                @endforelse
            </div>
            @if($withoutImages->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">{{ $withoutImages->links() }}</div>
            @endif
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 font-medium">Возможные дубликаты</div>
        <div class="p-4 space-y-4">
            @forelse($duplicateGroups as $group)
            <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50/50 dark:bg-amber-900/10 p-4">
                <p class="text-sm font-medium text-gray-900 dark:text-white mb-2">Группа из {{ $group->count() }} карточек</p>
                <ul class="space-y-1">
                    @foreach($group as $product)
                    <li>
                        <a href="{{ route('admin.catalog.products.show', $product) }}" class="text-sm text-[#c3242a] hover:underline">
                            {{ $product->name }} ({{ $product->sku }})
                        </a>
                        <span class="text-xs text-gray-500">— {{ $product->manufacturerProfile?->short_name ?: $product->manufacturerProfile?->full_name ?: 'Производитель' }}</span>
                    </li>
                    @endforeach
                </ul>
            </div>
            @empty
            <p class="text-sm text-gray-500">Похожих дубликатов не обнаружено.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
