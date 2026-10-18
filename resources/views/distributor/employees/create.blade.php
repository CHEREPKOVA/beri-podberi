@extends('layouts.app')

@section('title', 'Добавить сотрудника')
@section('heading', 'Добавить сотрудника')

@section('content')
<div class="max-w-4xl space-y-6">
    @if($errors->any())
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <p class="text-sm text-gray-500 mb-6">После создания на указанную почту уйдёт приглашение со ссылкой для установки пароля.</p>
        <form method="POST" action="{{ route('distributor.employees.store') }}" class="space-y-6">
            @csrf
            @include('distributor.employees._form')
            <div class="flex justify-end gap-3">
                <a href="{{ route('distributor.employees.index') }}" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">Отмена</a>
                <button type="submit" class="px-4 py-2 bg-[#c3242a] text-white rounded-lg hover:bg-[#a01e24] text-sm font-medium">Создать</button>
            </div>
        </form>
    </div>
</div>
@endsection
