<?php

return [
    'params_kind' => 'distributor_employee',

    'invitation_ttl_days' => 7,

    'orders' => [
        'none' => 'Нет доступа',
        'view' => 'Просмотр',
        'manage' => 'Управление',
    ],

    'purchases' => [
        'none' => 'Нет доступа',
        'view' => 'Просмотр',
        'manage' => 'Управление',
    ],

    'products' => [
        'none' => 'Нет доступа',
        'view' => 'Просмотр',
        'manage' => 'Управление',
    ],

    'default_permissions' => [
        'orders' => 'none',
        'purchases' => 'none',
        'products' => 'none',
        'prices' => false,
        'warehouses' => false,
        'employees' => false,
        'discounts' => false,
        'import' => false,
        'manufacturer_chat' => false,
        'company_profile' => false,
    ],

    'default_roles' => [
        [
            'name' => 'Администратор компании',
            'permissions' => [
                'orders' => 'manage',
                'purchases' => 'manage',
                'products' => 'manage',
                'prices' => true,
                'warehouses' => true,
                'employees' => true,
                'discounts' => true,
                'import' => true,
                'manufacturer_chat' => true,
                'company_profile' => true,
            ],
        ],
        [
            'name' => 'Менеджер по продажам',
            'permissions' => [
                'orders' => 'manage',
                'purchases' => 'none',
                'products' => 'view',
                'prices' => false,
                'warehouses' => false,
                'employees' => false,
                'discounts' => false,
                'import' => false,
                'manufacturer_chat' => true,
                'company_profile' => false,
            ],
        ],
        [
            'name' => 'Менеджер по закупкам',
            'permissions' => [
                'orders' => 'none',
                'purchases' => 'manage',
                'products' => 'view',
                'prices' => false,
                'warehouses' => false,
                'employees' => false,
                'discounts' => false,
                'import' => false,
                'manufacturer_chat' => true,
                'company_profile' => false,
            ],
        ],
        [
            'name' => 'Оператор каталога',
            'permissions' => [
                'orders' => 'none',
                'purchases' => 'none',
                'products' => 'manage',
                'prices' => true,
                'warehouses' => true,
                'employees' => false,
                'discounts' => false,
                'import' => true,
                'manufacturer_chat' => false,
                'company_profile' => false,
            ],
        ],
        [
            'name' => 'Логист / менеджер склада',
            'permissions' => [
                'orders' => 'manage',
                'purchases' => 'view',
                'products' => 'view',
                'prices' => false,
                'warehouses' => true,
                'employees' => false,
                'discounts' => false,
                'import' => false,
                'manufacturer_chat' => false,
                'company_profile' => false,
            ],
        ],
        [
            'name' => 'Поддержка клиентов',
            'permissions' => [
                'orders' => 'manage',
                'purchases' => 'none',
                'products' => 'view',
                'prices' => false,
                'warehouses' => false,
                'employees' => false,
                'discounts' => false,
                'import' => false,
                'manufacturer_chat' => true,
                'company_profile' => false,
            ],
        ],
    ],
];
