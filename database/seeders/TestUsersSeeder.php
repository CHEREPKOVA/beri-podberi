<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUsersSeeder extends Seeder
{
    /**
     * Тестовые пользователи для каждой роли.
     * Пароль у всех: password
     */
    private const TEST_PASSWORD = 'password';

    private const USERS = [
        Role::SLUG_ADMIN => [
            'name' => 'Администратор',
            'email' => 'admin@cherepkova.ru',
        ],
        Role::SLUG_MANAGER => [
            'name' => 'Менеджер Тестовый',
            'email' => 'manager@test.com',
        ],
        Role::SLUG_MANUFACTURER => [
            'name' => 'Производитель Тестовый',
            'email' => 'manufacturer@test.com',
        ],
        Role::SLUG_DISTRIBUTOR => [
            'name' => 'Дистрибьютор Тестовый',
            'email' => 'distributor@test.com',
        ],
        Role::SLUG_END_COMPANY => [
            'name' => 'Компания Тестовая',
            'email' => 'company@test.com',
        ],
        Role::SLUG_COMPANY_EMPLOYEE => [
            'name' => 'Сотрудник Тестовый',
            'email' => 'employee@test.com',
        ],
    ];

    public function run(): void
    {
        foreach (self::USERS as $roleSlug => $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make(self::TEST_PASSWORD),
                    'email_verified_at' => now(),
                ]
            );

            $role = Role::findBySlug($roleSlug);
            if ($role) {
                $user->roles()->sync([$role->id]);
            }
        }

        // Пользователь с несколькими ролями (дистрибьютор + конечный покупатель), с названиями компаний для модального окна
        $multiRoleUser = User::updateOrCreate(
            ['email' => 'distributor-buyer@test.com'],
            [
                'name' => 'Дистрибьютор и Покупатель',
                'password' => Hash::make(self::TEST_PASSWORD),
                'email_verified_at' => now(),
            ]
        );
        $distributorRole = Role::findBySlug(Role::SLUG_DISTRIBUTOR);
        $endCompanyRole = Role::findBySlug(Role::SLUG_END_COMPANY);
        $multiRoleUser->roles()->sync([
            $distributorRole->id => ['company_name' => 'АккумТрейд'],
            $endCompanyRole->id => ['company_name' => 'СТО-АвтоПлюс'],
        ]);
    }
}
