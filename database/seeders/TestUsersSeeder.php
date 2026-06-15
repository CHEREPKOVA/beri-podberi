<?php

namespace Database\Seeders;

use App\Models\EndCompanyDeliveryAddress;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUsersSeeder extends Seeder
{
    /**
     * Тестовые пользователи по ролям (производитель с демо-кабинетом — в FactoryDemoSeeder).
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
        Role::SLUG_ANALYST => [
            'name' => 'Аналитик Тестовый',
            'email' => 'analyst@test.com',
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

            if ($roleSlug === Role::SLUG_END_COMPANY) {
                $this->ensureEndCompanyCatalogRegion($user);
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
            $distributorRole->id => ['company_name' => 'АккумТрейд Опт'],
            $endCompanyRole->id => ['company_name' => 'СТО-АвтоПлюс'],
        ]);
        $this->ensureEndCompanyCatalogRegion($multiRoleUser);
    }

    private function ensureEndCompanyCatalogRegion(User $user): void
    {
        $moscow = Region::query()->where('name', 'Москва')->first();
        if ($moscow === null) {
            return;
        }

        $profile = $user->getOrCreateEndCompanyProfile();

        EndCompanyDeliveryAddress::updateOrCreate(
            [
                'end_company_profile_id' => $profile->id,
                'name' => 'Основной адрес',
            ],
            [
                'address' => 'г. Москва, ул. Тестовая, 1',
                'region_id' => $moscow->id,
                'is_default' => true,
            ],
        );
    }
}
