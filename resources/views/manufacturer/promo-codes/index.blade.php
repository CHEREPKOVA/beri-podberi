@extends('layouts.app')

@section('title', 'Промокоды')
@section('heading', 'Промокоды')

@section('content')
@php $canManage = $canManage ?? false; @endphp
<div class="space-y-6">
    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 px-4 py-3 rounded-lg">{{ session('success') }}</div>
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
                    class="w-72 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                <select name="discount_type" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                    <option value="">Все типы</option>
                    @foreach($types as $value => $label)
                        <option value="{{ $value }}" @selected(request('discount_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="status" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                    <option value="">Все статусы</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
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
                        <th class="px-4 py-3"></th>
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
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('manufacturer.promo-codes.edit', $code) }}" class="text-sm text-[#c3242a] hover:underline">Редактировать</a>
                                @if($canManage)
                                    <form method="POST" action="{{ route('manufacturer.promo-codes.toggle', $code) }}" class="inline ml-2">@csrf<button class="text-sm text-gray-500 hover:underline">{{ $code->status === 'active' ? 'Отключить' : 'Включить' }}</button></form>
                                @endif
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
</div>
@endsection
