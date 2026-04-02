@extends('layouts.app')

@section('title', 'Каталог товаров')
@section('heading', 'Управление каталогом')

@section('content')
<div class="space-y-6">
    @include('admin.partials.flash')

    <p class="text-sm text-gray-600 dark:text-gray-400 max-w-4xl">
        Раздел для централизованного администрирования каталога маркетплейса: структура категорий, карточки товаров, свойства,
        фильтрация, связи аналогов и контроль качества данных. Изменения сразу отражаются в пользовательских кабинетах.
    </p>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('admin.catalog.categories.index') }}" class="block rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm hover:border-[#c3242a]/40 hover:shadow-md transition">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-1">Категории</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Единая структура и вложенность ассортимента</p>
        </a>
        <a href="{{ route('admin.catalog.products.index') }}" class="block rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm hover:border-[#c3242a]/40 hover:shadow-md transition">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-1">Товары</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Проверка и редактирование карточек</p>
        </a>
        <a href="{{ route('admin.catalog.attributes.index') }}" class="block rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm hover:border-[#c3242a]/40 hover:shadow-md transition">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-1">Свойства и фильтры</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Характеристики, обязательность, фильтры</p>
        </a>
        <a href="{{ route('admin.catalog.analogs.index') }}" class="block rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm hover:border-[#c3242a]/40 hover:shadow-md transition">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-1">Аналоги</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Связи взаимозаменяемости товаров</p>
        </a>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5">
            <p class="text-xs uppercase text-gray-500">Категории</p>
            <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $stats['categories_total'] }}</p>
            <p class="text-sm text-gray-500">Активных: {{ $stats['categories_active'] }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5">
            <p class="text-xs uppercase text-gray-500">Товары</p>
            <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $stats['products_total'] }}</p>
            <p class="text-sm text-gray-500">Всего карточек в каталоге</p>
        </div>
        <a href="{{ route('admin.catalog.quality') }}" class="rounded-xl border border-yellow-200 dark:border-yellow-700 bg-yellow-50/60 dark:bg-yellow-900/20 p-5 hover:shadow-sm transition">
            <p class="text-xs uppercase text-yellow-800 dark:text-yellow-300">Контроль качества</p>
            <p class="mt-2 text-sm text-yellow-900 dark:text-yellow-200">
                Без категории: {{ $stats['products_without_category'] }}, без свойств: {{ $stats['products_without_attributes'] }}, без изображений: {{ $stats['products_without_images'] }}
            </p>
            <p class="mt-2 text-sm font-medium text-[#c3242a]">Открыть отчёт →</p>
        </a>
    </div>
</div>
@endsection
