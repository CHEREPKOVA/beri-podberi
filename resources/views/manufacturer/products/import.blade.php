@extends('layouts.app')

@section('title', 'Импорт товаров')
@section('heading', 'Импорт товаров')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">

    <a href="{{ route('manufacturer.products.index') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">
        <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Назад к списку товаров
    </a>

    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
        {{ session('error') }}
    </div>
    @endif

    @if(session('warning'))
    <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded-lg">
        {{ session('warning') }}
        @if(session('import_errors'))
        <ul class="mt-2 text-sm list-disc list-inside">
            @foreach(session('import_errors') as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
        @endif
    </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Загрузка файла</h2>

        <form action="{{ route('manufacturer.products.import') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Формат файла
                </label>
                <div class="grid grid-cols-2 gap-4">
                    <label class="relative flex cursor-pointer rounded-lg border p-4 transition-colors border-gray-200 hover:border-[#c3242a] has-[:checked]:border-[#c3242a] has-[:checked]:bg-red-50">
                        <input type="radio" name="format" value="csv" class="sr-only" checked />
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                                <span class="text-xs font-bold text-green-700">CSV</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">CSV файл</p>
                                <p class="text-xs text-gray-500">Разделитель: точка с запятой</p>
                            </div>
                        </div>
                    </label>

                    <label class="relative flex cursor-pointer rounded-lg border p-4 transition-colors border-gray-200 hover:border-[#c3242a] has-[:checked]:border-[#c3242a] has-[:checked]:bg-red-50">
                        <input type="radio" name="format" value="yml" class="sr-only" />
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-orange-100 flex items-center justify-center">
                                <span class="text-xs font-bold text-orange-700">YML</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">YML файл</p>
                                <p class="text-xs text-gray-500">Яндекс.Маркет формат</p>
                            </div>
                        </div>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Файл для импорта
                </label>
                <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-8 text-center" x-data="{ fileName: '' }">
                    <input type="file" name="file" id="import-file" required accept=".csv,.txt,.xml,.yml" class="hidden"
                        @change="fileName = $event.target.files[0]?.name || ''" />
                    <label for="import-file" class="cursor-pointer">
                        <svg class="mx-auto w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                            <span x-show="!fileName">Нажмите для выбора файла</span>
                            <span x-show="fileName" x-text="fileName" class="font-medium text-[#c3242a]"></span>
                        </p>
                        <p class="text-xs text-gray-500">CSV, XML, YML до 10 МБ</p>
                    </label>
                </div>
                @error('file')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end">
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2 bg-[#c3242a] text-white rounded-lg hover:bg-[#a01e24] font-medium">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    Импортировать
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Формат CSV файла</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            Файл должен содержать заголовки столбцов в первой строке. Обязательные поля: <strong>Артикул</strong> и <strong>Наименование</strong>.
        </p>
        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 overflow-x-auto">
            <table class="text-sm">
                <thead>
                    <tr class="text-left text-gray-500">
                        <th class="pr-6 pb-2">Столбец</th>
                        <th class="pr-6 pb-2">Обязательный</th>
                        <th class="pb-2">Описание</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700 dark:text-gray-300">
                    <tr><td class="pr-6 py-1">Артикул / SKU</td><td class="pr-6">Да</td><td>Уникальный идентификатор товара</td></tr>
                    <tr><td class="pr-6 py-1">Наименование / Name</td><td class="pr-6">Да</td><td>Название товара</td></tr>
                    <tr><td class="pr-6 py-1">Категория / Category</td><td class="pr-6">Нет</td><td>Название категории</td></tr>
                    <tr><td class="pr-6 py-1">Цена / Price</td><td class="pr-6">Нет</td><td>Базовая цена</td></tr>
                    <tr><td class="pr-6 py-1">Остаток / Stock</td><td class="pr-6">Нет</td><td>Количество на основном складе</td></tr>
                    <tr><td class="pr-6 py-1">Описание / Description</td><td class="pr-6">Нет</td><td>Описание товара</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
