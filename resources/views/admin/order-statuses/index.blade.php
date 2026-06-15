@extends('layouts.app')
@section('title', 'Статусы заказов')
@section('heading', 'Статусы заказов')
@section('content')
<div class="space-y-6" x-data="{ showDeleteModal: false, deleteFormAction: '', deleteMessage: '' }">
    @include('admin.partials.flash')
    <a href="{{ route('admin.directories.index') }}" class="text-sm text-gray-500 hover:text-[#c3242a]">← Справочники</a>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border">
        <div class="p-6 border-b flex flex-col sm:flex-row sm:justify-between gap-4">
            <form method="GET" class="flex gap-3"><input type="text" name="search" value="{{ request('search') }}" placeholder="Поиск…" class="px-4 py-2 border rounded-lg text-sm bg-white dark:bg-gray-700" /><button class="px-4 py-2 bg-gray-100 rounded-lg text-sm">Найти</button></form>
            <a href="{{ route('admin.order-statuses.create') }}" class="px-4 py-2 bg-[#c3242a] text-white rounded-lg text-sm">Добавить</a>
        </div>
        <table class="min-w-full divide-y"><thead class="bg-gray-50 dark:bg-gray-700"><tr><th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Название</th><th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Код</th><th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Финальный</th><th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Статус</th><th class="w-28"></th></tr></thead>
        <tbody class="divide-y">@forelse($statuses as $status)<tr class="group hover:bg-gray-50 dark:hover:bg-gray-700/50">
            <td class="px-4 py-3 text-sm"><a href="{{ route('admin.order-statuses.edit', $status) }}" class="font-medium hover:text-[#c3242a]">{{ $status->name }}</a></td>
            <td class="px-4 py-3 text-sm font-mono">{{ $status->slug }}</td>
            <td class="px-4 py-3 text-sm">{{ $status->is_terminal ? 'Да' : 'Нет' }}</td>
            <td class="px-4 py-3">@if($status->is_active)<span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-800">Активен</span>@else<span class="text-xs px-2 py-0.5 rounded-full bg-gray-200">Неактивен</span>@endif</td>
            <td class="px-4 py-3"><div class="flex justify-end gap-1 opacity-0 group-hover:opacity-100"><a href="{{ route('admin.order-statuses.edit', $status) }}" class="p-1.5 text-gray-400 hover:text-[#c3242a]"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></a><button type="button" @click="deleteFormAction='{{ route('admin.order-statuses.destroy', $status) }}'; deleteMessage={{ json_encode('Удалить статус «'.$status->name.'»?') }}; showDeleteModal=true" class="p-1.5 text-gray-400 hover:text-red-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></div></td>
        </tr>@empty<tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Нет записей.</td></tr>@endforelse</tbody></table>
        @if($statuses->hasPages())<div class="px-4 py-3 border-t">{{ $statuses->links() }}</div>@endif
    </div>
    @include('admin.partials.delete_modal')
</div>
@endsection
