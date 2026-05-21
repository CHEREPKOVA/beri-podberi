<?php

namespace Database\Seeders;

use App\Models\DistributorContact;
use App\Models\DistributorProfile;
use App\Models\EndCompanyDeliveryAddress;
use App\Models\EndCompanyProfile;
use App\Models\ManufacturerProfile;
use App\Models\PlatformOrder;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PartnerCatalogDemoSeeder extends Seeder
{
    public function run(): void
    {
        $regions = Region::active()->take(8)->get();
        if ($regions->isEmpty()) {
            $this->command->warn('Нет регионов для PartnerCatalogDemoSeeder.');

            return;
        }

        $categories = ProductCategory::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->take(3)
            ->get();

        $manufacturer = ManufacturerProfile::query()->first();
        if (! $manufacturer) {
            $this->command->warn('Нет производителя. Сначала FactoryDemoSeeder.');

            return;
        }

        $distributors = [
            ['name' => 'ООО «СеверОпт»', 'short' => 'СеверОпт', 'inn' => '7701001001', 'email' => 'sever-opt@test.com'],
            ['name' => 'ООО «ЮгТрейд»', 'short' => 'ЮгТрейд', 'inn' => '7701001002', 'email' => 'yug-trade@test.com'],
            ['name' => 'ИП Козлов А.В.', 'short' => 'Козлов', 'inn' => '7701001003', 'email' => 'kozlov-dist@test.com'],
        ];

        foreach ($distributors as $index => $data) {
            $role = Role::findBySlug(Role::SLUG_DISTRIBUTOR);
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['short'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );
            if ($role) {
                $user->roles()->syncWithoutDetaching([$role->id => ['company_name' => $data['short']]]);
            }

            $profile = DistributorProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name' => $data['name'],
                    'short_name' => $data['short'],
                    'inn' => $data['inn'],
                    'legal_address' => 'г. Москва, ул. Примерная, д. '.($index + 1),
                    'description' => 'Дистрибьютор автотоваров.',
                ],
            );

            $region = $regions[$index % $regions->count()];
            $profile->regions()->sync([$region->id => ['is_primary' => true]]);

            if ($categories->isNotEmpty()) {
                $profile->productCategories()->sync(
                    $categories->take(2)->pluck('id')->all()
                );
            }

            if (! $profile->contacts()->exists()) {
                DistributorContact::create([
                    'distributor_profile_id' => $profile->id,
                    'full_name' => 'Менеджер '.$data['short'],
                    'position' => 'Менеджер по закупкам',
                    'phone' => '+7 (495) 100-10-0'.$index,
                    'email' => 'manager@'.str_replace('@', '-', $data['email']),
                    'is_primary' => true,
                ]);
            }

            PlatformOrder::updateOrCreate(
                ['order_number' => 'ORD-D-'.$profile->id.'-001'],
                [
                    'distributor_profile_id' => $profile->id,
                    'manufacturer_profile_id' => $index === 0 ? $manufacturer->id : null,
                    'total_amount' => 125000 + ($index * 10000),
                    'status' => PlatformOrder::STATUS_COMPLETED,
                    'ordered_at' => now()->subDays(10 - $index),
                ],
            );
        }

        $companies = [
            ['name' => 'ООО «АвтоСервис Плюс»', 'short' => 'АвтоСервис+', 'inn' => '7702002001', 'activity' => 'СТО', 'email' => 'autoservice@test.com'],
            ['name' => 'ООО «Магазин АКБ»', 'short' => 'Магазин АКБ', 'inn' => '7702002002', 'activity' => 'Розничная торговля', 'email' => 'akb-shop@test.com'],
        ];

        foreach ($companies as $index => $data) {
            $role = Role::findBySlug(Role::SLUG_END_COMPANY);
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['short'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );
            if ($role) {
                $user->roles()->syncWithoutDetaching([$role->id => ['company_name' => $data['short']]]);
            }

            $profile = EndCompanyProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name' => $data['name'],
                    'short_name' => $data['short'],
                    'inn' => $data['inn'],
                    'activity_type' => $data['activity'],
                    'description' => 'Конечный покупатель на платформе.',
                ],
            );

            $region = $regions[($index + 2) % $regions->count()];
            EndCompanyDeliveryAddress::updateOrCreate(
                [
                    'end_company_profile_id' => $profile->id,
                    'name' => 'Основной адрес',
                ],
                [
                    'address' => 'г. '.$region->name.', ул. Центральная, 1',
                    'region_id' => $region->id,
                    'is_default' => true,
                ],
            );

            PlatformOrder::updateOrCreate(
                ['order_number' => 'ORD-C-'.$profile->id.'-001'],
                [
                    'end_company_profile_id' => $profile->id,
                    'manufacturer_profile_id' => $manufacturer->id,
                    'total_amount' => 45000,
                    'status' => PlatformOrder::STATUS_PROCESSING,
                    'ordered_at' => now()->subDays(3),
                ],
            );
        }

        $this->command->info('PartnerCatalogDemoSeeder: дистрибьюторы и конечные компании для каталога партнёров.');
    }
}
