@extends('layouts.app')

@section('title', 'Типы компаний')
@section('heading', 'Типы компаний')

@section('content')
<div class="space-y-6" x-data="{ showDeleteModal: false, deleteFormAction: '', deleteMessage: '' }">
    @include('admin.partials.flash')
    <a href="{{ route('admin.directories.index') }}" class="text-sm text-gray-500 hover:text-[#c3242a]">← Справочники</a>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <form method="GET" class="flex flex-wrap items-center gap-3">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Поиск…"
                    class="w-56 sm:w-72 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm" />
                <button type="submit" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm">Найти</button>
            </form>
            <a href="{{ route('admin.company-types.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-[#c3242a] text-white rounded-lg hover:bg-[#a01e24] text-sm font-medium shrink-0">Добавить</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Название</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Код</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Статус</th>
                        <th class="w-28 px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($companyTypes as $companyType)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 group">
                        <td class="px-4 py-3 text-sm"><a href="{{ route('admin.company-types.edit', $companyType) }}" class="font-medium text-gray-900 dark:text-white hover:text-[#c3242a]">{{ $companyType->name }}</a></td>
                        <td class="px-4 py-3 text-sm font-mono text-gray-600">{{ $companyType->slug }}</td>
                        <td class="px-4 py-3">@if($companyType->is_active)<span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-800">Активен</span>@else<span class="text-xs px-2 py-0.5 rounded-full bg-gray-200 text-gray-700">Неактивен</span>@endif</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-1 opacity-0 group-hover:opacity-100">
                                <a href="{{ route('admin.company-types.edit', $companyType) }}" class="p-1.5 text-gray-400 hover:text-[#c3242a] rounded-lg"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></a>
                                <button type="button" @click="deleteFormAction = '{{ route('admin.company-types.destroy', $companyType) }}'; deleteMessage = {{ json_encode('Удалить тип «' . $companyType->name . '»?') }}; showDeleteModal = true" class="p-1.5 text-gray-400 hover:text-red-600 rounded-lg"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">Нет записей.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($companyTypes->hasPages())<div class="px-4 py-3 border-t">{{ $companyTypes->links() }}</div>@endif
    </div>
    @include('admin.partials.delete_modal')
</div>
@endsection
