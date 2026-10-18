<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Типы сотрудников производителя (role_user.company_type)
    |--------------------------------------------------------------------------
    */
    'types' => [
        'admin' => 'Администратор производителя',
        'sales_manager' => 'Менеджер продаж',
        'accountant' => 'Бухгалтер',
        'warehouse' => 'Складской персонал',
    ],

    /*
    |--------------------------------------------------------------------------
    | Права в каталоге партнёров (дистрибьюторы / компании)
    |--------------------------------------------------------------------------
    */
    'partner_catalog' => [
        'admin' => ['view', 'add', 'remove', 'exclusive', 'orders'],
        'sales_manager' => ['view', 'add', 'orders'],
        'accountant' => ['view'],
        'warehouse' => [],
    ],

    'params_kind' => 'manufacturer_employee',

    'invitation_ttl_days' => 7,

    'orders' => [
        'none' => 'Нет доступа',
        'view' => 'Просмотр',
        'create' => 'Создание',
        'confirm' => 'Подтверждение',
        'defects' => 'Работа с браками',
    ],

    'products' => [
        'none' => 'Нет доступа',
        'view' => 'Просмотр',
        'manage' => 'Управление',
    ],

    'discounts' => [
        'none' => 'Нет доступа',
        'view' => 'Просмотр',
        'manage' => 'Создание и редактирование',
    ],

    'default_permissions' => [
        'orders' => 'none',
        'products' => 'none',
        'warehouses' => false,
        'company_profile' => false,
        'claims' => false,
        'discounts' => 'none',
    ],

    'default_roles' => [
        [
            'name' => 'Менеджер заказов',
            'permissions' => [
                'orders' => 'confirm',
                'products' => 'view',
                'warehouses' => false,
                'company_profile' => false,
                'claims' => true,
                'discounts' => 'view',
            ],
        ],
        [
            'name' => 'Менеджер продаж',
            'permissions' => [
                'orders' => 'confirm',
                'products' => 'view',
                'warehouses' => false,
                'company_profile' => false,
                'claims' => false,
                'discounts' => 'manage',
            ],
        ],
        [
            'name' => 'Менеджер склада',
            'permissions' => [
                'orders' => 'view',
                'products' => 'view',
                'warehouses' => true,
                'company_profile' => false,
                'claims' => false,
                'discounts' => 'none',
            ],
        ],
        [
            'name' => 'Оператор',
            'permissions' => [
                'orders' => 'create',
                'products' => 'view',
                'warehouses' => false,
                'company_profile' => false,
                'claims' => false,
                'discounts' => 'none',
            ],
        ],
        [
            'name' => 'Бухгалтер',
            'permissions' => [
                'orders' => 'view',
                'products' => 'view',
                'warehouses' => false,
                'company_profile' => true,
                'claims' => false,
                'discounts' => 'view',
            ],
        ],
    ],
];
