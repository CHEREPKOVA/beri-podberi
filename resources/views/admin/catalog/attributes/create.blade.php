@extends('layouts.app')

@section('title', 'Новое свойство')
@section('heading', 'Создание свойства')

@section('content')
<div class="space-y-6">
    <a href="{{ route('admin.catalog.attributes.index') }}" class="text-sm text-gray-500 hover:text-[#c3242a]">← Свойства</a>
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
        <form method="POST" action="{{ route('admin.catalog.attributes.store') }}">
            @csrf
            @include('admin.partials.form-errors')
            @include('admin.catalog.attributes._form')
            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('admin.catalog.attributes.index') }}" class="px-4 py-2 text-sm text-gray-500">Отмена</a>
                <button class="px-4 py-2 bg-[#c3242a] text-white rounded-lg text-sm">Сохранить</button>
            </div>
        </form>
    </div>
</div>
@endsection
