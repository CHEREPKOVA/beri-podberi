<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['slug' => Role::SLUG_ADMIN, 'name' => 'Администратор платформы', 'description' => 'Полный доступ к CMS', 'sort_order' => 1],
            ['slug' => Role::SLUG_MANAGER, 'name' => 'Менеджер платформы', 'description' => 'Оператор поддержки, ограниченный доступ к CMS', 'sort_order' => 2],
            ['slug' => Role::SLUG_MANUFACTURER, 'name' => 'Производитель (завод)', 'description' => 'Управление товарами и заказами от дистрибьюторов', 'sort_order' => 3],
            ['slug' => Role::SLUG_DISTRIBUTOR, 'name' => 'Дистрибьютор', 'description' => 'Закупка у производителей, заказы от компаний', 'sort_order' => 4],
            ['slug' => Role::SLUG_END_COMPANY, 'name' => 'Конечная компания (Магазин / СТО)', 'description' => 'Заказы у дистрибьюторов', 'sort_order' => 5],
            ['slug' => Role::SLUG_COMPANY_EMPLOYEE, 'name' => 'Сотрудник организации', 'description' => 'Внутренний сотрудник в рамках корпоративного аккаунта (производитель, дистрибьютор, конечная компания). Права задаёт администратор организации.', 'sort_order' => 6],
        ];

        foreach ($roles as $data) {
            Role::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }
}
