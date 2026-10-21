@extends('layouts.app')

@section('title', 'Промокоды')
@section('heading', 'Промокоды')

@section('content')
@php
    $canManage = $canManage ?? false;
    $canDelete = $canDelete ?? false;
@endphp
<div x-data="{
    showDeleteModal: false,
    deleteFormAction: '',
    deleteMessage: ''
}" class="space-y-6">
    @if(session('success'))
        <div class="bg-red-50 dark:bg-red-900/20 border border-[#c3242a]/30 dark:border-[#c3242a]/40 text-[#a01e24] dark:text-red-100 px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg">{{ session('error') }}</div>
    @endif

    <div class="flex gap-2 border-b border-gray-200 dark:border-gray-700">
        <a href="{{ route('manufacturer.discounts.index') }}" class="px-4 py-2 text-sm font-medium border-b-2 -mb-px border-transparent text-gray-500 hover:text-gray-700">Наборы скидок</a>
        <a href="{{ route('manufacturer.promo-codes.index') }}" class="px-4 py-2 text-sm font-medium border-b-2 -mb-px border-[#c3242a] text-[#c3242a]">Промокоды</a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700 space-y-4">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Список промокодов</h2>
                    <p class="text-sm text-gray-500 mt-1">Коды активируются дистрибьютором вручную при оформлении закупки.</p>
                </div>
                @if($canManage)
                    <a href="{{ route('manufacturer.promo-codes.create') }}" class="inline-flex items-center px-4 py-2 bg-[#c3242a] text-white rounded-lg hover:bg-[#a01e24] text-sm font-medium">Создать промокод</a>
                @endif
            </div>
            <form method="GET" class="flex flex-wrap items-center gap-3">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Поиск по коду или названию"
                    class="w-72 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent">
                <x-styled-select name="discount_type" size="sm">
                    <option value="">Все типы</option>
                    @foreach($types as $value => $label)
                        <option value="{{ $value }}" @selected(request('discount_type') === $value)>{{ $label }}</option>
                    @endforeach
                </x-styled-select>
                <x-styled-select name="status" size="sm">
                    <option value="">Все статусы</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </x-styled-select>
                <button class="px-4 py-2 bg-gray-900 text-white rounded-lg text-sm">Найти</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Код</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Тип скидки</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Срок действия</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Использований</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Статус</th>
                        <th class="px-4 py-3 w-36"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($codes as $code)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="px-4 py-3">
                                <a href="{{ route('manufacturer.promo-codes.edit', $code) }}" class="font-mono font-medium text-gray-900 dark:text-white hover:text-[#c3242a]">{{ $code->code }}</a>
                                @if($code->name)<p class="text-xs text-gray-500">{{ $code->name }}</p>@endif
                            </td>
                            <td class="px-4 py-3">{{ $code->typeLabel() }} · {{ $code->sizeLabel() }}</td>
                            <td class="px-4 py-3">{{ $code->periodLabel() }}</td>
                            <td class="px-4 py-3">{{ $code->uses_count }}@if($code->max_uses)/{{ $code->max_uses }}@endif</td>
                            <td class="px-4 py-3">
                                @php $status = $code->effectiveStatus(); @endphp
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium
                                    {{ $status === 'active' ? 'bg-green-100 text-green-800' : ($status === 'expired' ? 'bg-gray-200 text-gray-700' : 'bg-amber-100 text-amber-800') }}">
                                    {{ $code->statusLabel() }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-0.5">
                                    <a href="{{ route('manufacturer.promo-codes.edit', $code) }}" class="p-1.5 text-gray-400 hover:text-[#c3242a] rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600" title="{{ $canManage ? 'Редактировать' : 'Открыть' }}">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    @if($canManage)
                                        <form method="POST" action="{{ route('manufacturer.promo-codes.toggle', $code) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="p-1.5 text-gray-400 hover:text-[#c3242a] rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600" title="{{ $code->status === 'active' ? 'Отключить' : 'Включить' }}">
                                                @if($code->status === 'active')
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                                @else
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                @endif
                                            </button>
                                        </form>
                                    @endif
                                    @if($canDelete && ! $code->isLocked())
                                        <button type="button"
                                            @click="deleteFormAction = '{{ route('manufacturer.promo-codes.destroy', $code) }}'; deleteMessage = {{ json_encode('Удалить промокод «'.$code->code.'»?') }}; showDeleteModal = true"
                                            class="p-1.5 text-gray-400 hover:text-red-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600"
                                            title="Удалить">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">Промокоды ещё не созданы.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($codes->hasPages())
            <div class="p-4 border-t border-gray-200 dark:border-gray-700">{{ $codes->links() }}</div>
        @endif
    </div>

    @if($canDelete)
    <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showDeleteModal = false">
        <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-xl p-6" @click.stop>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Удаление промокода</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-6" x-text="deleteMessage"></p>
            <form :action="deleteFormAction" method="POST" class="flex justify-end gap-3">
                @csrf
                @method('DELETE')
                <button type="button" @click="showDeleteModal = false" class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">Отмена</button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium">Удалить</button>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection
