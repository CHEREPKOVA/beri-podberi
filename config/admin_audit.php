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
    | Источники событий журнала
    |--------------------------------------------------------------------------
    */
    'sources' => [
        'admin_action' => ['label' => 'Админ-панель'],
        'partnership' => ['label' => 'Партнёрства'],
        'distributor_product' => ['label' => 'Товары дистрибьютора'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Модули для фильтрации журнала (ключ — значение фильтра module)
    |--------------------------------------------------------------------------
    */
    'modules' => [
        'staff' => [
            'label' => 'Сотрудники',
            'prefixes' => ['admin.staff.'],
            'sources' => ['admin_action'],
        ],
        'companies' => [
            'label' => 'Компании',
            'prefixes' => ['admin.companies.'],
            'sources' => ['admin_action'],
        ],
        'catalog' => [
            'label' => 'Каталог',
            'prefixes' => ['admin.catalog.'],
            'sources' => ['admin_action'],
        ],
        'directories' => [
            'label' => 'Справочники и настройки',
            'prefixes' => [
                'admin.directories.',
                'admin.regions.',
                'admin.federal-districts.',
                'admin.company-types.',
                'admin.platform-roles.',
                'admin.order-statuses.',
                'admin.claim-statuses.',
                'admin.delivery-methods.',
                'admin.transport-companies.',
                'admin.warehouse-types.',
                'admin.unit-types.',
                'admin.document-types.',
                'admin.system-settings.',
            ],
            'sources' => ['admin_action'],
        ],
        'partnership' => [
            'label' => 'Партнёрства',
            'sources' => ['partnership'],
        ],
        'distributor_product' => [
            'label' => 'Товары дистрибьютора',
            'sources' => ['distributor_product'],
        ],
    ],

    'partnership_action_labels' => [
        'added' => 'Дистрибьютор добавлен в партнёры',
        'exclusive_assigned' => 'Назначен эксклюзивный дистрибьютор',
        'status_changed' => 'Изменён статус партнёрства',
        'removed' => 'Дистрибьютор исключён из партнёров',
    ],

    'distributor_product_action_labels' => [
        'created' => 'Создан товар дистрибьютора',
        'updated' => 'Обновлён товар дистрибьютора',
        'published' => 'Товар опубликован',
        'unpublished' => 'Товар снят с публикации',
        'price_changed' => 'Изменена цена',
        'stock_changed' => 'Изменён остаток',
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
        'admin.catalog.products.image.delete' => 'Удаление изображения товара',
        'admin.catalog.products.image.primary' => 'Назначение главного изображения товара',
        'admin.catalog.products.document.delete' => 'Удаление документа товара',

        'admin.catalog.attributes.store' => 'Создание свойства товара',
        'admin.catalog.attributes.update' => 'Изменение свойства товара',
        'admin.catalog.attributes.destroy' => 'Удаление свойства товара',

        'admin.catalog.analogs.import' => 'Импорт аналогов',
        'admin.catalog.analogs.update' => 'Изменение аналогов товара',

        'admin.regions.store' => 'Создание региона',
        'admin.regions.update' => 'Изменение региона',
        'admin.regions.destroy' => 'Удаление региона',

        'admin.federal-districts.store' => 'Создание федерального округа',
        'admin.federal-districts.update' => 'Изменение федерального округа',
        'admin.federal-districts.destroy' => 'Удаление федерального округа',

        'admin.company-types.store' => 'Создание типа компании',
        'admin.company-types.update' => 'Изменение типа компании',
        'admin.company-types.destroy' => 'Удаление типа компании',

        'admin.platform-roles.store' => 'Создание роли платформы',
        'admin.platform-roles.update' => 'Изменение роли платформы',
        'admin.platform-roles.destroy' => 'Удаление роли платформы',

        'admin.order-statuses.store' => 'Создание статуса заказа',
        'admin.order-statuses.update' => 'Изменение статуса заказа',
        'admin.order-statuses.destroy' => 'Удаление статуса заказа',

        'admin.claim-statuses.store' => 'Создание статуса претензии',
        'admin.claim-statuses.update' => 'Изменение статуса претензии',
        'admin.claim-statuses.destroy' => 'Удаление статуса претензии',

        'admin.delivery-methods.store' => 'Создание способа доставки',
        'admin.delivery-methods.update' => 'Изменение способа доставки',
        'admin.delivery-methods.destroy' => 'Удаление способа доставки',

        'admin.transport-companies.store' => 'Создание транспортной компании',
        'admin.transport-companies.update' => 'Изменение транспортной компании',
        'admin.transport-companies.destroy' => 'Удаление транспортной компании',

        'admin.warehouse-types.store' => 'Создание типа склада',
        'admin.warehouse-types.update' => 'Изменение типа склада',
        'admin.warehouse-types.destroy' => 'Удаление типа склада',

        'admin.unit-types.store' => 'Создание единицы измерения',
        'admin.unit-types.update' => 'Изменение единицы измерения',
        'admin.unit-types.destroy' => 'Удаление единицы измерения',

        'admin.document-types.store' => 'Создание типа документа',
        'admin.document-types.update' => 'Изменение типа документа',
        'admin.document-types.destroy' => 'Удаление типа документа',
    ],
];
