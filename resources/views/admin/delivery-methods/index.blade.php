@extends('layouts.app')

@section('title', 'Способы доставки')
@section('heading', 'Способы доставки')

@section('content')
<div class="space-y-6" x-data="{ showDeleteModal: false, deleteFormAction: '', deleteMessage: '' }">
    @include('admin.partials.flash')

    <a href="{{ route('admin.directories.index') }}" class="text-sm text-gray-500 hover:text-[#c3242a]">← Справочники</a>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex flex-row justify-end items-center gap-4">
            <a href="{{ route('admin.delivery-methods.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-[#c3242a] text-white rounded-lg hover:bg-[#a01e24] text-sm font-medium shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Добавить
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Название</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Slug</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Трекинг</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Порядок</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Статус</th>
                        <th class="w-28 px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($methods as $method)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 group">
                        <td class="px-4 py-3 text-sm">
                            <a href="{{ route('admin.delivery-methods.edit', $method) }}" class="font-medium text-gray-900 dark:text-white hover:text-[#c3242a] dark:hover:text-red-400">{{ $method->name }}</a>
                        </td>
                        <td class="px-4 py-3 text-sm font-mono text-gray-600 dark:text-gray-300">{{ $method->slug }}</td>
                        <td class="px-4 py-3 text-sm">{{ $method->requires_tracking ? 'Да' : 'Нет' }}</td>
                        <td class="px-4 py-3 text-sm">{{ $method->sort_order }}</td>
                        <td class="px-4 py-3">
                            @if($method->is_active)
                            <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300">Активен</span>
                            @else
                            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-200 dark:bg-gray-600 text-gray-700">Неактивен</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <a href="{{ route('admin.delivery-methods.edit', $method) }}" class="p-1.5 text-gray-400 hover:text-[#c3242a] rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                <button type="button"
                                    @click="deleteFormAction = '{{ route('admin.delivery-methods.destroy', $method) }}'; deleteMessage = {{ json_encode('Удалить способ доставки «' . $method->name . '»?') }}; showDeleteModal = true"
                                    class="p-1.5 text-gray-400 hover:text-red-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Нет записей.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($methods->hasPages())
        <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">{{ $methods->links() }}</div>
        @endif
    </div>

    <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showDeleteModal = false">
        <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-xl p-6" @click.stop>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Удаление</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-6" x-text="deleteMessage"></p>
            <form :action="deleteFormAction" method="POST" class="flex justify-end gap-3">
                @csrf
                @method('DELETE')
                <button type="button" @click="showDeleteModal = false" class="px-4 py-2 text-gray-600">Отмена</button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium">Удалить</button>
            </form>
        </div>
    </div>
</div>
@endsection
