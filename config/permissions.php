<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Каталог прав (RBAC)
    |--------------------------------------------------------------------------
    */
    'catalog' => [
        [
            'slug' => 'dashboard.view',
            'name' => 'Просмотр дашборда',
            'description' => 'Доступ к главной странице админ-панели',
            'group_key' => 'dashboard',
            'sort_order' => 10,
        ],
        [
            'slug' => 'staff.manage',
            'name' => 'Управление сотрудниками',
            'description' => 'Создание, редактирование, блокировка и отзыв доступа админ-пользователей',
            'group_key' => 'staff',
            'sort_order' => 20,
        ],
        [
            'slug' => 'audit.view',
            'name' => 'Просмотр журнала действий',
            'description' => 'Доступ к журналу действий админ-панели для контроля и внутренних проверок',
            'group_key' => 'audit',
            'sort_order' => 25,
        ],
        [
            'slug' => 'companies.manage',
            'name' => 'Управление компаниями',
            'description' => 'Управление карточками компаний и их сотрудниками',
            'group_key' => 'companies',
            'sort_order' => 30,
        ],
        [
            'slug' => 'catalog.manage',
            'name' => 'Управление каталогом',
            'description' => 'Редактирование каталога товаров и классификаторов',
            'group_key' => 'catalog',
            'sort_order' => 40,
        ],
        [
            'slug' => 'directories.manage',
            'name' => 'Управление справочниками',
            'description' => 'Редактирование справочников и системных настроек',
            'group_key' => 'directories',
            'sort_order' => 50,
        ],
        [
            'slug' => 'support.manage',
            'name' => 'Работа с поддержкой',
            'description' => 'Обработка обращений и коммуникаций',
            'group_key' => 'support',
            'sort_order' => 60,
        ],
        [
            'slug' => 'stats.view',
            'name' => 'Просмотр статистики',
            'description' => 'Доступ к аналитике и отчётам без изменений данных',
            'group_key' => 'stats',
            'sort_order' => 70,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Права по ролям (базовые шаблоны)
    |--------------------------------------------------------------------------
    */
    'role_defaults' => [
        'admin' => ['*'],
        'manager' => [
            'dashboard.view',
            'companies.manage',
            'support.manage',
            'stats.view',
            'audit.view',
        ],
        'analyst' => [
            'dashboard.view',
            'stats.view',
        ],
    ],
];
