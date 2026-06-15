<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Какие HTTP-методы считаем изменяющими состояние
    |--------------------------------------------------------------------------
    */
    'methods' => ['POST', 'PUT', 'PATCH', 'DELETE'],

    /*
    |--------------------------------------------------------------------------
    | Какие поля никогда не пишем в аудит-лог
    |--------------------------------------------------------------------------
    */
    'hidden_input_keys' => [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        '_token',
    ],

    /*
    |--------------------------------------------------------------------------
    | Человекочитаемые названия действий (ключ — имя маршрута)
    |--------------------------------------------------------------------------
    */
    'action_labels' => [
        '_default' => 'Действие в панели управления',

        'admin.staff.store' => 'Добавление сотрудника',
        'admin.staff.update' => 'Изменение сотрудника',
        'admin.staff.destroy' => 'Отзыв доступа сотрудника',
        'admin.staff.suspend' => 'Блокировка сотрудника',
        'admin.staff.activate' => 'Разблокировка сотрудника',

        'admin.companies.store' => 'Создание компании',
        'admin.companies.update' => 'Обновление данных компании',
        'admin.companies.destroy' => 'Удаление компании',
        'admin.companies.users.update' => 'Изменение пользователя компании',
        'admin.companies.users.suspend' => 'Блокировка пользователя компании',
        'admin.companies.users.activate' => 'Разблокировка пользователя компании',
        'admin.companies.users.reset-password' => 'Сброс пароля пользователя компании',
        'admin.companies.users.delete' => 'Удаление пользователя из компании',

        'admin.system-settings.update' => 'Изменение системных настроек',

        'admin.catalog.categories.store' => 'Создание категории каталога',
        'admin.catalog.categories.update' => 'Изменение категории каталога',
        'admin.catalog.categories.destroy' => 'Удаление категории каталога',

        'admin.catalog.products.update' => 'Изменение товара каталога',

        'admin.catalog.attributes.store' => 'Создание свойства товара',
        'admin.catalog.attributes.update' => 'Изменение свойства товара',
        'admin.catalog.attributes.destroy' => 'Удаление свойства товара',

        'admin.catalog.analogs.import' => 'Импорт аналогов',
        'admin.catalog.analogs.update' => 'Изменение аналогов товара',

        'admin.regions.store' => 'Создание региона',
        'admin.regions.update' => 'Изменение региона',
        'admin.regions.destroy' => 'Удаление региона',

        'admin.delivery-methods.store' => 'Создание способа доставки',
        'admin.delivery-methods.update' => 'Изменение способа доставки',
        'admin.delivery-methods.destroy' => 'Удаление способа доставки',

        'admin.transport-companies.store' => 'Создание транспортной компании',
        'admin.transport-companies.update' => 'Изменение транспортной компании',
        'admin.transport-companies.destroy' => 'Удаление транспортной компании',

        'admin.unit-types.store' => 'Создание единицы измерения',
        'admin.unit-types.update' => 'Изменение единицы измерения',
        'admin.unit-types.destroy' => 'Удаление единицы измерения',
    ],
];
