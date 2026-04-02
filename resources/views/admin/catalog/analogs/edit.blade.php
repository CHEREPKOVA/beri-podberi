@extends('layouts.app')

@section('title', 'Связи аналогов')
@section('heading', 'Редактирование связей аналогов')

@section('content')
<div class="space-y-6">
    @include('admin.partials.flash')
    <a href="{{ route('admin.catalog.analogs.index') }}" class="text-sm text-gray-500 hover:text-[#c3242a]">← Аналоги</a>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
        <h2 class="font-semibold text-gray-900 dark:text-white">{{ $product->name }} <span class="text-gray-500">({{ $product->sku }})</span></h2>
        <p class="text-sm text-gray-500 mt-1">Отметьте взаимозаменяемые позиции. Связи сохраняются двусторонне.</p>

        <form method="POST" action="{{ route('admin.catalog.analogs.update', $product) }}" class="mt-6">
            @csrf
            @method('PUT')
            @include('admin.partials.form-errors')
            <label class="block text-sm mb-2">Аналоги</label>
            <select name="analog_ids[]" multiple class="w-full min-h-[320px] px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700">
                @foreach($allProducts as $item)
                <option value="{{ $item->id }}" @selected(in_array($item->id, $selectedIds))>
                    {{ $item->name }} ({{ $item->sku }})
                </option>
                @endforeach
            </select>
            @include('admin.partials.field-error', ['field' => 'analog_ids'])
            <div class="mt-6 flex justify-end">
                <button class="px-4 py-2 bg-[#c3242a] text-white rounded-lg text-sm">Сохранить связи</button>
            </div>
        </form>
    </div>
</div>
@endsection
