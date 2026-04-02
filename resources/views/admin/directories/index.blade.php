@extends('layouts.app')

@section('title', 'Справочники')
@section('heading', 'Справочники и системные данные')

@section('content')
<div class="space-y-6">
    @include('admin.partials.flash')

    <p class="text-sm text-gray-600 dark:text-gray-400 max-w-3xl">
        Централизованное управление значениями, которые используются в личных кабинетах и модулях платформы.
        Удаление записей, уже привязанных к данным производителей или номенклатуры, недоступно — используйте деактивацию.
    </p>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <a href="{{ route('admin.regions.index') }}" class="block rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm hover:border-[#c3242a]/40 hover:shadow-md transition">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-1">Регионы</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">География работы, федеральные округа</p>
        </a>
        <a href="{{ route('admin.delivery-methods.index') }}" class="block rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm hover:border-[#c3242a]/40 hover:shadow-md transition">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-1">Способы доставки</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Самовывоз, ТК, собственный транспорт и др.</p>
        </a>
        <a href="{{ route('admin.transport-companies.index') }}" class="block rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm hover:border-[#c3242a]/40 hover:shadow-md transition">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-1">Транспортные компании</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Справочник ТК и ссылки на трекинг</p>
        </a>
        <a href="{{ route('admin.unit-types.index') }}" class="block rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm hover:border-[#c3242a]/40 hover:shadow-md transition">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-1">Единицы измерения</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Штуки, упаковки, вес и объём</p>
        </a>
        <a href="{{ route('admin.system-settings.index') }}" class="block rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm hover:border-[#c3242a]/40 hover:shadow-md transition">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-1">Глобальные настройки</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Уведомления, тайминги, лимиты, безопасность</p>
        </a>

    </div>
</div>
@endsection
