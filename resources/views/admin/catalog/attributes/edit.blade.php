@extends('layouts.app')

@section('title', 'Редактирование свойства')
@section('heading', 'Редактирование свойства')

@section('content')
<div class="space-y-6" x-data="{ showDeleteModal: false }">
    @include('admin.partials.flash')
    <a href="{{ route('admin.catalog.attributes.index') }}" class="text-sm text-gray-500 hover:text-[#c3242a]">← Свойства</a>
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
        <form method="POST" action="{{ route('admin.catalog.attributes.update', $attribute) }}">
            @csrf
            @method('PUT')
            @include('admin.partials.form-errors')
            @include('admin.catalog.attributes._form')
            <div class="mt-6 flex justify-between items-center">
                <button type="submit" class="px-4 py-2 bg-[#c3242a] text-white rounded-lg text-sm">Сохранить</button>
            </div>
        </form>
        <button type="button" @click="showDeleteModal = true" class="mt-3 px-4 py-2 text-sm text-red-600">Удалить свойство</button>
    </div>

    <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showDeleteModal = false">
        <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-xl p-6" @click.stop>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Подтверждение удаления</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                Удалить свойство «{{ $attribute->name }}»? Если по нему есть значения в карточках, удаление будет отклонено.
            </p>
            <form method="POST" action="{{ route('admin.catalog.attributes.destroy', $attribute) }}" class="flex justify-end gap-3">
                @csrf
                @method('DELETE')
                <button type="button" @click="showDeleteModal = false" class="px-4 py-2 text-gray-600 dark:text-gray-300">Отмена</button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium">Удалить</button>
            </form>
        </div>
    </div>
</div>
@endsection
