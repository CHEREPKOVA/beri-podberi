<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(RegionSeeder::class);
        $this->call(DeliveryMethodSeeder::class);
        $this->call(TransportCompanySeeder::class);
        $this->call(ProductCategorySeeder::class);
        $this->call(UnitTypeSeeder::class);
        $this->call(ProductAttributeSeeder::class);
        $this->call(TestUsersSeeder::class);
        $this->call(FactoryDemoSeeder::class);

        // User::factory(10)->create();

        User::factory()
            ->withRoles(\App\Models\Role::SLUG_END_COMPANY)
            ->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
    }
}
