@extends('layouts.app')

@section('title', 'Редактирование региона')
@section('heading', 'Редактирование региона')

@section('content')
<div class="max-w-xl space-y-6">
    <a href="{{ route('admin.regions.index') }}" class="text-sm text-gray-500 hover:text-[#c3242a]">← К списку регионов</a>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <form method="POST" action="{{ route('admin.regions.update', $region) }}" class="space-y-5">
            @csrf
            @method('PUT')
            @include('admin.regions._form', ['districts' => $districts, 'region' => $region])

            <div class="flex gap-3 pt-2">
                <button type="submit" class="px-4 py-2 bg-[#c3242a] text-white rounded-lg hover:bg-[#a01e24] text-sm font-medium">Сохранить</button>
                <a href="{{ route('admin.regions.index') }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">Отмена</a>
            </div>
        </form>
    </div>
</div>
@endsection
