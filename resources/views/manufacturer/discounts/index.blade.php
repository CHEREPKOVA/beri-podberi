@extends('layouts.app')

@section('title', 'Наборы скидок')
@section('heading', 'Наборы скидок')

@section('content')
@php
    $canManage = $canManage ?? false;
    $canDelete = $canDelete ?? false;
@endphp
<div x-data="{
    selected: [],
    selectAll: false,
    showDeleteModal: false,
    hovered: null,
    toggleAll() {
        this.selected = this.selectAll
            ? [...document.querySelectorAll('[data-set-id]')].map(el => el.dataset.setId)
            : [];
    }
}" class="space-y-6">
    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg">{{ session('error') }}</div>
    @endif

    <div class="flex gap-2 border-b border-gray-200 dark:border-gray-700">
        <a href="{{ route('manufacturer.discounts.index') }}" class="px-4 py-2 text-sm font-medium border-b-2 -mb-px border-[#c3242a] text-[#c3242a]">Наборы скидок</a>
        <a href="{{ route('manufacturer.promo-codes.index') }}" class="px-4 py-2 text-sm font-medium border-b-2 -mb-px border-transparent text-gray-500 hover:text-gray-700">Промокоды</a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700 space-y-4">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Список наборов скидок</h2>
                    <p class="text-sm text-gray-500 mt-1">Автоматические скидки для дистрибьюторов по категориям, объёму, региону и индивидуальным условиям.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if($canManage)
                        <a href="{{ route('manufacturer.discounts.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-[#c3242a] text-white rounded-lg hover:bg-[#a01e24] text-sm font-medium">
                            Создать набор скидок
                        </a>
                    @endif
                    <a href="{{ route('manufacturer.discounts.export', request()->query()) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm hover:bg-gray-50 dark:hover:bg-gray-700">
                        Экспортировать
                    </a>
                </div>
            </div>

            <form method="GET" class="flex flex-wrap items-center gap-3">
                <div class="relative">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Поиск по названию, категории, типу клиента"
                        class="w-72 pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
                    <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <select name="type" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                    <option value="">Все типы</option>
                    @foreach($types as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="status" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                    <option value="">Все статусы</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="distributor_id" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm max-w-[16rem]">
                    <option value="">Все дистрибьюторы</option>
                    @foreach($distributors as $distributor)
                        <option value="{{ $distributor['id'] }}" @selected((string) request('distributor_id') === (string) $distributor['id'])>{{ $distributor['name'] }}</option>
                    @endforeach
                </select>
                <button type="submit" class="px-4 py-2 bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900 rounded-lg text-sm">Найти</button>
                @if(request()->hasAny(['search', 'type', 'status', 'distributor_id', 'category_id']))
                    <a href="{{ route('manufacturer.discounts.index') }}" class="text-sm text-gray-500 hover:text-[#c3242a]">Сбросить</a>
                @endif
            </form>

            @if($canManage)
            <div class="flex flex-wrap gap-2" x-show="selected.length > 0" x-cloak>
                <form method="POST" action="{{ route('manufacturer.discounts.bulk') }}" class="inline">
                    @csrf
                    <input type="hidden" name="action" value="disable">
                    <template x-for="id in selected" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
                    <button type="submit" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Отключить выбранные</button>
                </form>
                @if($canDelete)
                <button type="button" @click="showDeleteModal = true" class="px-3 py-1.5 border border-red-300 text-red-700 rounded-lg text-sm hover:bg-red-50">Удалить выбранные</button>
                @endif
            </div>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        @if($canManage)
                        <th class="px-4 py-3 w-10">
                            <input type="checkbox" x-model="selectAll" @change="toggleAll()" class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
                        </th>
                        @endif
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Название набора</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Тип скидки</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Размер скидки</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Применимо к</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Статус</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Создан</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Изменён</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Дистрибьюторы</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($rows as $row)
                        @php $set = $row['set']; @endphp
                        <tr data-set-id="{{ $set->id }}"
                            @mouseenter="hovered = {{ $set->id }}"
                            @mouseleave="hovered = null"
                            class="hover:bg-gray-50 dark:hover:bg-gray-700/40 relative">
                            @if($canManage)
                            <td class="px-4 py-3">
                                <input type="checkbox" value="{{ $set->id }}" x-model="selected" class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
                            </td>
                            @endif
                            <td class="px-4 py-3">
                                <a href="{{ route('manufacturer.discounts.edit', $set) }}" class="font-medium text-gray-900 dark:text-white hover:text-[#c3242a]">{{ $set->name }}</a>
                                <div class="mt-1 flex flex-wrap gap-2 text-xs" x-show="hovered === {{ $set->id }}" x-cloak>
                                    <a href="{{ route('manufacturer.discounts.edit', $set) }}" class="text-[#c3242a] hover:underline">Открыть</a>
                                    @if($canManage)
                                        <a href="{{ route('manufacturer.discounts.edit', $set) }}" class="text-gray-500 hover:underline">Редактировать</a>
                                        <form method="POST" action="{{ route('manufacturer.discounts.copy', $set) }}" class="inline">@csrf<button class="text-gray-500 hover:underline">Копировать</button></form>
                                        <form method="POST" action="{{ route('manufacturer.discounts.toggle', $set) }}" class="inline">@csrf<button class="text-gray-500 hover:underline">{{ $set->effectiveStatus() === 'active' ? 'Отключить' : 'Включить' }}</button></form>
                                        @if($canDelete)
                                            <form method="POST" action="{{ route('manufacturer.discounts.destroy', $set) }}" class="inline" onsubmit="return confirm('Удалить набор «{{ $set->name }}»?')">
                                                @csrf @method('DELETE')
                                                <button class="text-red-600 hover:underline">Удалить</button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $set->typeLabel() }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $set->sizeLabel() }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300 max-w-xs">{{ $set->appliesLabel() }}</td>
                            <td class="px-4 py-3">
                                @php $status = $set->effectiveStatus(); @endphp
                                @if($status === 'active')
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Активен</span>
                                @elseif($status === 'scheduled')
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Запланирован</span>
                                @elseif($status === 'completed')
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-200 text-gray-700">Завершён</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Отключен</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $set->created_at?->format('d.m.Y') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $set->updated_at?->format('d.m.Y') }}</td>
                            <td class="px-4 py-3">{{ $row['distributor_count'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-10 text-center text-gray-500">Наборы скидок ещё не созданы.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($sets->hasPages())
            <div class="p-4 border-t border-gray-200 dark:border-gray-700">{{ $sets->links() }}</div>
        @endif
    </div>

    @if($canDelete)
    <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 max-w-md w-full space-y-4">
            <h3 class="text-lg font-semibold">Удалить выбранные наборы?</h3>
            <p class="text-sm text-gray-500">Наборы, которые используются в активных заказах, будут пропущены.</p>
            <form method="POST" action="{{ route('manufacturer.discounts.bulk') }}" class="flex justify-end gap-2">
                @csrf
                <input type="hidden" name="action" value="delete">
                <template x-for="id in selected" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
                <button type="button" @click="showDeleteModal = false" class="px-4 py-2 text-sm">Отмена</button>
                <button type="submit" class="px-4 py-2 bg-[#c3242a] text-white rounded-lg text-sm">Удалить</button>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection
