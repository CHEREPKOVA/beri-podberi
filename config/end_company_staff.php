<?php

return [
    'params_kind' => 'end_company_employee',

    'invitation_ttl_days' => 7,

    'orders' => [
        'none' => 'Нет доступа',
        'view' => 'Просмотр',
        'create' => 'Оформление заказов',
        'manage' => 'Оформление и согласование',
    ],

    'default_permissions' => [
        'orders' => 'none',
        'own_orders_only' => false,
        'catalog' => false,
        'company_profile' => false,
        'employees' => false,
    ],

    'default_roles' => [
        [
            'name' => 'Администратор компании',
            'permissions' => [
                'orders' => 'manage',
                'own_orders_only' => false,
                'catalog' => true,
                'company_profile' => true,
                'employees' => true,
            ],
        ],
        [
            'name' => 'Менеджер по закупкам',
            'permissions' => [
                'orders' => 'manage',
                'own_orders_only' => true,
                'catalog' => true,
                'company_profile' => false,
                'employees' => false,
            ],
        ],
        [
            'name' => 'Менеджер филиала',
            'permissions' => [
                'orders' => 'create',
                'own_orders_only' => false,
                'catalog' => true,
                'company_profile' => false,
                'employees' => false,
            ],
        ],
        [
            'name' => 'Просмотр заказов',
            'permissions' => [
                'orders' => 'view',
                'own_orders_only' => false,
                'catalog' => false,
                'company_profile' => false,
                'employees' => false,
            ],
        ],
        [
            'name' => 'Сотрудник технической службы',
            'permissions' => [
                'orders' => 'none',
                'own_orders_only' => false,
                'catalog' => true,
                'company_profile' => false,
                'employees' => false,
            ],
        ],
    ],
];
