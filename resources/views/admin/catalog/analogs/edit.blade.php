@extends('layouts.app')

@section('title', 'Связи аналогов')
@section('heading', 'Редактирование связей аналогов')

@section('content')
<div class="space-y-6">
    @include('admin.partials.flash')
    <a href="{{ route('admin.catalog.analogs.index') }}" class="text-sm text-gray-500 hover:text-[#c3242a]">← Аналоги</a>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
        <h2 class="font-semibold text-gray-900 dark:text-white">{{ $product->name }} <span class="text-gray-500">({{ $product->sku }})</span></h2>
        <p class="text-sm text-gray-500 mt-1">Найдите и добавьте взаимозаменяемые позиции из совместимых категорий. Связи сохраняются двусторонне.</p>

        @if($incompatibleSelected->isNotEmpty())
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
            <p class="font-medium">Обнаружены несовместимые связи ({{ $incompatibleSelected->count() }})</p>
            <p class="mt-1">Уберите их из списка и сохраните форму: {{ $incompatibleSelected->pluck('name')->implode(', ') }}</p>
        </div>
        @endif

        <form method="POST" action="{{ route('admin.catalog.analogs.update', $product) }}" class="mt-6">
            @csrf
            @method('PUT')
            @include('admin.partials.form-errors')
            @include('shared._analog_picker', [
                'selectedAnalogs' => $selectedAnalogs,
                'searchUrl' => route('admin.catalog.analogs.search', $product),
            ])
            @include('admin.partials.field-error', ['field' => 'analog_ids'])
            <p class="mt-2 text-xs text-gray-500">В поиске — только товары из той же категории или с пересечением дополнительных категорий.</p>
            <div class="mt-6 flex justify-end">
                <button class="px-4 py-2 bg-[#c3242a] text-white rounded-lg text-sm">Сохранить связи</button>
            </div>
        </form>
    </div>
</div>
@endsection
