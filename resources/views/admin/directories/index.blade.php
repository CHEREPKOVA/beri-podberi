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

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('admin.regions.index') }}" class="block rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm hover:border-[#c3242a]/40 hover:shadow-md transition">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-1">Регионы</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">География работы, привязка к федеральным округам</p>
        </a>
        <a href="{{ route('admin.federal-districts.index') }}" class="block rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm hover:border-[#c3242a]/40 hover:shadow-md transition">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-1">Федеральные округа</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Группировка регионов по округам РФ</p>
        </a>
        <a href="{{ route('admin.company-types.index') }}" class="block rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm hover:border-[#c3242a]/40 hover:shadow-md transition">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-1">Типы компаний</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Производитель, дистрибьютор, конечная компания</p>
        </a>
        <a href="{{ route('admin.platform-roles.index') }}" class="block rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm hover:border-[#c3242a]/40 hover:shadow-md transition">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-1">Роли пользователей</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Роли платформы и личных кабинетов</p>
        </a>
        <a href="{{ route('admin.order-statuses.index') }}" class="block rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm hover:border-[#c3242a]/40 hover:shadow-md transition">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-1">Статусы заказов</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Жизненный цикл заказа на платформе</p>
        </a>
        <a href="{{ route('admin.claim-statuses.index') }}" class="block rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm hover:border-[#c3242a]/40 hover:shadow-md transition">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-1">Статусы претензий</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Этапы рассмотрения претензий</p>
        </a>
        <a href="{{ route('admin.delivery-methods.index') }}" class="block rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm hover:border-[#c3242a]/40 hover:shadow-md transition">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-1">Способы доставки</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Самовывоз, ТК, собственный транспорт и др.</p>
        </a>
        <a href="{{ route('admin.transport-companies.index') }}" class="block rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm hover:border-[#c3242a]/40 hover:shadow-md transition">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-1">Транспортные компании</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Справочник ТК и ссылки на трекинг</p>
        </a>
        <a href="{{ route('admin.warehouse-types.index') }}" class="block rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm hover:border-[#c3242a]/40 hover:shadow-md transition">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-1">Типы складов</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Классификация складов производителя и дистрибьютора</p>
        </a>
        <a href="{{ route('admin.unit-types.index') }}" class="block rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm hover:border-[#c3242a]/40 hover:shadow-md transition">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-1">Единицы измерения</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Штуки, упаковки, вес и объём</p>
        </a>
        <a href="{{ route('admin.document-types.index') }}" class="block rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm hover:border-[#c3242a]/40 hover:shadow-md transition">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-1">Типы документов</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Классификаторы файлов профилей и товаров</p>
        </a>
    </div>
</div>
@endsection
