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
    deleteFormAction: '',
    deleteMessage: '',
    toggleAll() {
        this.selected = this.selectAll
            ? [...document.querySelectorAll('[data-set-id]')].map(el => el.dataset.setId)
            : [];
    }
}" class="space-y-6">
    @if(session('success'))
        <div class="bg-red-50 dark:bg-red-900/20 border border-[#c3242a]/30 dark:border-[#c3242a]/40 text-[#a01e24] dark:text-red-100 px-4 py-3 rounded-lg">{{ session('success') }}</div>
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
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    @if($canManage)
                        <a href="{{ route('distributor.discounts.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#c3242a] text-white rounded-lg hover:bg-[#a01e24] text-sm font-medium whitespace-nowrap">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Создать
                        </a>
                    @endif
                    <a href="{{ route('distributor.discounts.export', request()->query()) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm hover:bg-gray-50 dark:hover:bg-gray-700 whitespace-nowrap">
                        Экспорт
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
                <x-styled-select name="type" size="sm" @change="submit()">
                    <option value="">Все типы</option>
                    @foreach($types as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </x-styled-select>
                <x-styled-select name="status" size="sm" @change="submit()">
                    <option value="">Все статусы</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </x-styled-select>
                <x-styled-select name="period" size="sm" @change="submit()">
                    <option value="">Любой период</option>
                    @foreach($periods as $value => $label)
                        <option value="{{ $value }}" @selected(request('period') === $value)>{{ $label }}</option>
                    @endforeach
                </x-styled-select>
                <x-styled-select name="source" size="sm" @change="submit()">
                    <option value="">Все источники</option>
                    @foreach($sources as $value => $label)
                        <option value="{{ $value }}" @selected(request('source') === $value)>{{ $label }}</option>
                    @endforeach
                </x-styled-select>
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
                <button type="button" @click="deleteFormAction = ''; deleteMessage = ''; showDeleteModal = true" class="px-3 py-1.5 border border-red-300 text-red-700 rounded-lg text-sm hover:bg-red-50">Удалить выбранные</button>
            </div>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        @if($canManage)
                        <th class="px-4 py-3 w-10">
                            <label class="inline-flex items-center cursor-pointer select-none">
                                <input type="checkbox" x-model="selectAll" @change="toggleAll()" class="sr-only peer">
                                <x-brand-checkbox-mark />
                            </label>
                        </th>
                        @endif
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"><a href="{{ $sortUrl('name') }}" class="hover:text-[#c3242a]">Название</a></th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"><a href="{{ $sortUrl('type') }}" class="hover:text-[#c3242a]">Тип</a></th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Размер</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"><a href="{{ $sortUrl('period') }}" class="hover:text-[#c3242a]">Период</a></th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"><a href="{{ $sortUrl('status') }}" class="hover:text-[#c3242a]">Статус</a></th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Товары</th>
                        <th class="px-4 py-3 w-36"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($rows as $row)
                        @php
                            $set = $row['set'];
                            $status = $set->effectiveStatus();
                        @endphp
                        <tr data-set-id="{{ $set->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            @if($canManage)
                            <td class="px-4 py-3">
                                <label class="inline-flex items-center cursor-pointer select-none">
                                    <input type="checkbox" value="{{ $set->id }}" x-model="selected" class="sr-only peer">
                                    <x-brand-checkbox-mark />
                                </label>
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
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-0.5">
                                    <a href="{{ route('distributor.discounts.edit', $set) }}" class="p-1.5 text-gray-400 hover:text-[#c3242a] rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600" title="{{ $canManage ? 'Редактировать' : 'Открыть' }}">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    @if($canManage)
                                        <form method="POST" action="{{ route('distributor.discounts.copy', $set) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="p-1.5 text-gray-400 hover:text-[#c3242a] rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600" title="Копировать">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('distributor.discounts.toggle', $set) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="p-1.5 text-gray-400 hover:text-[#c3242a] rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600" title="{{ $set->status === 'active' ? 'Отключить' : 'Активировать' }}">
                                                @if($set->status === 'active')
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                                @else
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                @endif
                                            </button>
                                        </form>
                                        <button type="button"
                                            @click="deleteFormAction = '{{ route('distributor.discounts.destroy', $set) }}'; deleteMessage = {{ json_encode('Удалить набор «'.$set->name.'»?') }}; showDeleteModal = true"
                                            class="p-1.5 text-gray-400 hover:text-red-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600"
                                            title="Удалить">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canManage ? 8 : 7 }}" class="px-4 py-10 text-center text-gray-500">Наборы скидок ещё не созданы.</td>
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
    <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showDeleteModal = false">
        <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-xl p-6" @click.stop>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2" x-text="deleteFormAction ? 'Удаление набора' : 'Удалить выбранные наборы?'"></h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-6" x-text="deleteMessage || 'Наборы, которые используются в текущих заказах, будут пропущены.'"></p>
            <template x-if="deleteFormAction">
                <form :action="deleteFormAction" method="POST" class="flex justify-end gap-3">
                    @csrf
                    @method('DELETE')
                    <button type="button" @click="showDeleteModal = false; deleteFormAction = ''; deleteMessage = ''" class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">Отмена</button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium">Удалить</button>
                </form>
            </template>
            <template x-if="!deleteFormAction">
                <form method="POST" action="{{ route('distributor.discounts.bulk') }}" class="flex justify-end gap-3">
                    @csrf
                    <input type="hidden" name="action" value="delete">
                    <template x-for="id in selected" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
                    <button type="button" @click="showDeleteModal = false" class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">Отмена</button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium">Удалить</button>
                </form>
            </template>
        </div>
    </div>
    @endif
</div>
@endsection
