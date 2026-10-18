@extends('layouts.app')

@section('title', 'Наборы скидок')
@section('heading', 'Наборы скидок')

@section('content')
@php
    $canManage = $canManage ?? false;
    $sort = request('sort', 'updated_at');
    $dir = request('dir', 'desc');
    $nextDir = $dir === 'asc' ? 'desc' : 'asc';
    $sortUrl = function (string $column) use ($sort, $nextDir) {
        return request()->fullUrlWithQuery(['sort' => $column, 'dir' => request('sort') === $column ? $nextDir : 'asc']);
    };
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

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700 space-y-4">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Список наборов скидок</h2>
                    <p class="text-sm text-gray-500 mt-1">Скидочные программы для конечных компаний: товарные, категорийные, персональные и массовые.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if($canManage)
                        <a href="{{ route('distributor.discounts.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-[#c3242a] text-white rounded-lg hover:bg-[#a01e24] text-sm font-medium">
                            Создать набор скидок
                        </a>
                    @endif
                    <a href="{{ route('distributor.discounts.export', request()->query()) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm hover:bg-gray-50 dark:hover:bg-gray-700">
                        Экспортировать
                    </a>
                </div>
            </div>

            <form method="GET" class="flex flex-wrap items-center gap-3" x-data="{ submit() { $el.submit() } }">
                <div class="relative">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Название, артикул, клиент"
                        @input.debounce.400ms="submit()"
                        class="w-72 pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
                    <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <select name="type" @change="submit()" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                    <option value="">Все типы</option>
                    @foreach($types as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="status" @change="submit()" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                    <option value="">Все статусы</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="period" @change="submit()" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                    <option value="">Любой период</option>
                    @foreach($periods as $value => $label)
                        <option value="{{ $value }}" @selected(request('period') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="source" @change="submit()" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                    <option value="">Все источники</option>
                    @foreach($sources as $value => $label)
                        <option value="{{ $value }}" @selected(request('source') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @if(request()->hasAny(['search', 'type', 'status', 'period', 'source', 'category_id', 'company_id', 'sort', 'dir']))
                    <a href="{{ route('distributor.discounts.index') }}" class="text-sm text-gray-500 hover:text-[#c3242a]">Сбросить фильтры</a>
                @endif
            </form>

            @if($canManage)
            <div class="flex flex-wrap gap-2" x-show="selected.length > 0" x-cloak>
                <form method="POST" action="{{ route('distributor.discounts.bulk') }}" class="inline">
                    @csrf
                    <input type="hidden" name="action" value="disable">
                    <template x-for="id in selected" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
                    <button type="submit" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Отключить выбранные</button>
                </form>
                <button type="button" @click="showDeleteModal = true" class="px-3 py-1.5 border border-red-300 text-red-700 rounded-lg text-sm hover:bg-red-50">Удалить выбранные</button>
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
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"><a href="{{ $sortUrl('name') }}" class="hover:text-[#c3242a]">Название</a></th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"><a href="{{ $sortUrl('type') }}" class="hover:text-[#c3242a]">Тип</a></th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Размер</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"><a href="{{ $sortUrl('period') }}" class="hover:text-[#c3242a]">Период</a></th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"><a href="{{ $sortUrl('status') }}" class="hover:text-[#c3242a]">Статус</a></th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Товары</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Клиенты</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"><a href="{{ $sortUrl('updated_at') }}" class="hover:text-[#c3242a]">Изменён</a></th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Источник</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($rows as $row)
                        @php
                            $set = $row['set'];
                            $status = $set->effectiveStatus();
                        @endphp
                        <tr data-set-id="{{ $set->id }}"
                            @mouseenter="hovered = {{ $set->id }}"
                            @mouseleave="hovered = null"
                            class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            @if($canManage)
                            <td class="px-4 py-3">
                                <input type="checkbox" value="{{ $set->id }}" x-model="selected" class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
                            </td>
                            @endif
                            <td class="px-4 py-3">
                                <a href="{{ route('distributor.discounts.edit', $set) }}" class="inline-flex items-center gap-2 font-medium text-gray-900 dark:text-white hover:text-[#c3242a]">
                                    @if($status === 'active')
                                        <span class="w-2 h-2 rounded-full bg-green-500 shrink-0" title="Активна"></span>
                                    @elseif($status === 'scheduled')
                                        <span class="w-2 h-2 rounded-full bg-blue-500 shrink-0" title="Запланирована"></span>
                                    @elseif($status === 'completed')
                                        <span class="w-2 h-2 rounded-full bg-gray-400 shrink-0" title="Завершена"></span>
                                    @else
                                        <span class="w-2 h-2 rounded-full bg-amber-500 shrink-0" title="Отключена"></span>
                                    @endif
                                    @if($set->type === 'personal')
                                        <svg class="w-4 h-4 text-[#c3242a] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Персональная"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    @endif
                                    {{ $set->name }}
                                </a>
                                <div class="mt-1 flex flex-wrap gap-2 text-xs" x-show="hovered === {{ $set->id }}" x-cloak>
                                    <a href="{{ route('distributor.discounts.edit', $set) }}" class="text-[#c3242a] hover:underline">Открыть</a>
                                    @if($canManage)
                                        <form method="POST" action="{{ route('distributor.discounts.copy', $set) }}" class="inline">@csrf<button class="text-gray-500 hover:underline">Копировать</button></form>
                                        <form method="POST" action="{{ route('distributor.discounts.toggle', $set) }}" class="inline">@csrf<button class="text-gray-500 hover:underline">{{ $set->status === 'active' ? 'Отключить' : 'Активировать' }}</button></form>
                                        <form method="POST" action="{{ route('distributor.discounts.destroy', $set) }}" class="inline" onsubmit="return confirm('Удалить набор «{{ $set->name }}»?')">
                                            @csrf @method('DELETE')
                                            <button class="text-red-600 hover:underline">Удалить</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $set->typeLabel() }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $set->sizeLabel() }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ $set->periodLabel() }}</td>
                            <td class="px-4 py-3">
                                @if($status === 'active')
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Активна</span>
                                @elseif($status === 'scheduled')
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Запланирована</span>
                                @elseif($status === 'completed')
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-200 text-gray-700">Завершена</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Отключена</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $row['product_count'] }}</td>
                            <td class="px-4 py-3">{{ $row['client_label'] }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $set->updated_at?->format('d.m.Y H:i') }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $set->sourceLabel() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canManage ? 10 : 9 }}" class="px-4 py-10 text-center text-gray-500">Наборы скидок ещё не созданы.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($sets->hasPages())
            <div class="p-4 border-t border-gray-200 dark:border-gray-700">{{ $sets->links() }}</div>
        @endif
    </div>

    @if($canManage)
    <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 max-w-md w-full space-y-4">
            <h3 class="text-lg font-semibold">Удалить выбранные наборы?</h3>
            <p class="text-sm text-gray-500">Наборы, которые используются в текущих заказах, будут пропущены.</p>
            <form method="POST" action="{{ route('distributor.discounts.bulk') }}" class="flex justify-end gap-2">
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
