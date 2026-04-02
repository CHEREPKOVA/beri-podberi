@extends('layouts.app')

@section('title', 'Свойства товаров')
@section('heading', 'Свойства и фильтры')

@section('content')
<div class="space-y-6">
    @include('admin.partials.flash')
    <a href="{{ route('admin.catalog.index') }}" class="text-sm text-gray-500 hover:text-[#c3242a]">← Каталог</a>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between gap-3">
            <form method="GET" class="flex items-center gap-3">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Поиск по названию или slug" class="w-72 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm" />
                <button class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm">Найти</button>
            </form>
            <a href="{{ route('admin.catalog.attributes.create') }}" class="px-4 py-2 bg-[#c3242a] text-white rounded-lg text-sm">Добавить свойство</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Свойство</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Категория</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Тип</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Параметры</th>
                        <th class="w-24 px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($attributes as $attribute)
                    <tr>
                        <td class="px-4 py-3 text-sm">{{ $attribute->name }} <span class="text-gray-500">({{ $attribute->slug }})</span></td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $attribute->productCategory?->name ?? 'Глобальное' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ \App\Models\ProductAttribute::typeLabels()[$attribute->type] ?? $attribute->type }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">
                            {{ $attribute->is_required ? 'Обязательное' : 'Необязательное' }},
                            {{ $attribute->is_filterable ? 'Фильтр' : 'Без фильтра' }},
                            {{ $attribute->is_active ? 'Активно' : 'Неактивно' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.catalog.attributes.edit', $attribute) }}" class="text-sm text-[#c3242a] hover:underline">Изменить</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Свойства не найдены.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">{{ $attributes->links() }}</div>
    </div>
</div>
@endsection
