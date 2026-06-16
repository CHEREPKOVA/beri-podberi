<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(PermissionSeeder::class);
        $this->call(RegionSeeder::class);
        $this->call(DeliveryMethodSeeder::class);
        $this->call(TransportCompanySeeder::class);
        $this->call(ProductCategorySeeder::class);
        $this->call(UnitTypeSeeder::class);
        $this->call(ProductAttributeSeeder::class);
        $this->call(TestUsersSeeder::class);
        $this->call(FactoryDemoSeeder::class);
        $this->call(PartnerCatalogDemoSeeder::class);
        $this->call(DistributorProductsDemoSeeder::class);
        $this->call(MultiDistributorCatalogDemoSeeder::class);

        // User::factory(10)->create();

        $testUser = User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
        $endCompanyRole = Role::findBySlug(Role::SLUG_END_COMPANY);
        if ($endCompanyRole) {
            $testUser->roles()->sync([$endCompanyRole->id]);
        }
    }
}
