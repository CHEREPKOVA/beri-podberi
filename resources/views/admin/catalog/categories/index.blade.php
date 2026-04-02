@extends('layouts.app')

@section('title', 'Категории')
@section('heading', 'Категории каталога')

@section('content')
<div class="space-y-6" x-data="{ showDeleteModal: false, deleteFormAction: '', deleteMessage: '' }">
    @include('admin.partials.flash')
    <a href="{{ route('admin.catalog.index') }}" class="text-sm text-gray-500 hover:text-[#c3242a]">← Каталог</a>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between gap-3">
            <form method="GET" class="flex items-center gap-3">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Поиск по названию или slug" class="w-72 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm" />
                <button class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm">Найти</button>
            </form>
            <a href="{{ route('admin.catalog.categories.create') }}" class="px-4 py-2 bg-[#c3242a] text-white rounded-lg text-sm">Добавить категорию</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Название</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Slug</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Родитель</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Статус</th>
                        <th class="w-24 px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($categories as $category)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                        <td class="px-4 py-3 text-sm">{{ $category->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $category->slug }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $category->parent?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm">{{ $category->is_active ? 'Активна' : 'Неактивна' }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.catalog.categories.edit', $category) }}" class="text-sm text-[#c3242a] hover:underline">Изменить</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Категории не найдены.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">{{ $categories->links() }}</div>
    </div>
</div>
@endsection
