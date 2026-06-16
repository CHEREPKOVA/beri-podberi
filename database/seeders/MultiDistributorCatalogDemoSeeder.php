<?php

namespace Database\Seeders;

use App\Models\DistributorProduct;
use App\Models\DistributorProductStock;
use App\Models\DistributorProfile;
use App\Models\DistributorWarehouse;
use App\Models\ManufacturerDistributorPartnership;
use App\Models\ManufacturerProfile;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Демо-товар с несколькими дистрибьюторами в одном регионе (Москва).
 * Нужен для проверки блока «Информация о поставщиках» в карточке каталога.
 *
 * Запуск: php artisan db:seed --class=MultiDistributorCatalogDemoSeeder
 *
 * Вход: company@test.com / password → Каталог → найти товар по артикулу DEMO-MULTI-DIST
 */
class MultiDistributorCatalogDemoSeeder extends Seeder
{
    public const DEMO_SKU = 'DEMO-MULTI-DIST';

    /** @var list<array{email: string, warehouse: string, price: float, qty: int, shipping: string}> */
    private const DISTRIBUTORS = [
        [
            'email' => 'sever-opt@test.com',
            'warehouse' => 'Склад СеверОпт (Москва)',
            'price' => 4890.00,
            'qty' => 24,
            'shipping' => 'Стандартная отгрузка со склада',
        ],
        [
            'email' => 'yug-trade@test.com',
            'warehouse' => 'Склад ЮгТрейд (Москва)',
            'price' => 4650.00,
            'qty' => 15,
            'shipping' => 'Отгрузка по запросу, мин. партия 2 шт.',
        ],
        [
            'email' => 'kozlov-dist@test.com',
            'warehouse' => 'Склад Козлов (Москва)',
            'price' => 5120.00,
            'qty' => 8,
            'shipping' => 'Требуется отдельный заказ',
        ],
    ];

    public function run(): void
    {
        $moscow = Region::query()->where('name', 'Москва')->first();
        if ($moscow === null) {
            $this->command->error('MultiDistributorCatalogDemoSeeder: регион «Москва» не найден. Запустите RegionSeeder.');

            return;
        }

        $manufacturer = ManufacturerProfile::query()
            ->where('short_name', 'АккумТрейд')
            ->orWhere('inn', '7700000001')
            ->first();

        if ($manufacturer === null) {
            $this->command->error('MultiDistributorCatalogDemoSeeder: производитель АккумТрейд не найден. Запустите FactoryDemoSeeder.');

            return;
        }

        $category = ProductCategory::query()
            ->where('slug', 'akkumulyatory-dlya-legkovyh-avtomobiley')
            ->orWhere('name', 'Аккумуляторы для легковых автомобилей')
            ->first()
            ?? ProductCategory::query()->whereNotNull('parent_id')->where('is_active', true)->first();

        if ($category === null) {
            $this->command->error('MultiDistributorCatalogDemoSeeder: нет подходящей категории товаров.');

            return;
        }

        $product = Product::updateOrCreate(
            [
                'manufacturer_profile_id' => $manufacturer->id,
                'sku' => self::DEMO_SKU,
            ],
            [
                'name' => 'Аккумулятор 12В 60 А·ч — демо нескольких дистрибьюторов',
                'category_id' => $category->id,
                'description' => 'Тестовая позиция для проверки таблицы поставщиков в карточке каталога. '
                    .'Один товар, три дистрибьютора в Москве с разными ценами и остатками.',
                'base_price' => 4200.00,
                'min_order_quantity' => 1,
                'manufacturer_sku' => 'AKB-DEMO-60',
                'status' => Product::STATUS_ACTIVE,
                'show_in_catalog' => true,
                'published_at' => now(),
            ],
        );

        if (! $product->images()->exists()) {
            ProductImage::factory()->for($product)->primary()->create();
        }

        $linkedDistributors = 0;

        foreach (self::DISTRIBUTORS as $index => $config) {
            $profile = $this->ensureDistributorInMoscow($config['email'], $moscow->id, $index);
            if ($profile === null) {
                continue;
            }

            ManufacturerDistributorPartnership::updateOrCreate(
                [
                    'manufacturer_profile_id' => $manufacturer->id,
                    'distributor_profile_id' => $profile->id,
                ],
                [
                    'status' => ManufacturerDistributorPartnership::STATUS_ACTIVE,
                    'added_at' => now()->subMonths(2),
                ],
            );

            $warehouse = DistributorWarehouse::updateOrCreate(
                [
                    'distributor_profile_id' => $profile->id,
                    'name' => $config['warehouse'],
                ],
                [
                    'address' => 'г. Москва, складская зона '.($index + 1),
                    'region_id' => $moscow->id,
                    'type' => DistributorWarehouse::TYPE_MAIN,
                    'is_active' => true,
                    'shipping_conditions' => $config['shipping'],
                ],
            );

            $offer = DistributorProduct::updateOrCreate(
                [
                    'distributor_profile_id' => $profile->id,
                    'source_product_id' => $product->id,
                ],
                [
                    'manufacturer_profile_id' => $manufacturer->id,
                    'product_category_id' => $category->id,
                    'name' => $product->name,
                    'internal_sku' => 'D-MULTI-'.$profile->id,
                    'manufacturer_sku' => $product->manufacturer_sku,
                    'brand' => $manufacturer->short_name,
                    'retail_price' => $config['price'],
                    'purchase_price' => round($config['price'] * 0.78, 2),
                    'price_updated_at' => now(),
                    'status' => DistributorProduct::STATUS_ACTIVE,
                    'sync_source' => DistributorProduct::SYNC_MANUFACTURER,
                    'min_order_quantity' => $index === 1 ? 2 : 1,
                ],
            );

            DistributorProductStock::updateOrCreate(
                [
                    'distributor_product_id' => $offer->id,
                    'distributor_warehouse_id' => $warehouse->id,
                ],
                [
                    'quantity' => $config['qty'],
                    'reserved' => 0,
                    'stock_updated_at' => now(),
                ],
            );

            $linkedDistributors++;
        }

        $this->ensureEndCompanyBuyerRegion($moscow->id);

        $this->command->newLine();
        $this->command->info('MultiDistributorCatalogDemoSeeder: демо-товар с несколькими дистрибьюторами готов.');
        $this->command->line("  Товар: {$product->name}");
        $this->command->line('  Артикул: '.self::DEMO_SKU.' (id: '.$product->id.')');
        $this->command->line("  Дистрибьюторов в Москве: {$linkedDistributors}");
        $this->command->line('  Проверка: войти как company@test.com / password → Каталог → карточка товара.');
        $this->command->line('  Или поиск в каталоге: «демо нескольких дистрибьюторов».');
    }

    private function ensureDistributorInMoscow(string $email, int $moscowId, int $index): ?DistributorProfile
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $role = Role::findBySlug(Role::SLUG_DISTRIBUTOR);
            if ($role === null) {
                return null;
            }

            $shortNames = ['СеверОпт', 'ЮгТрейд', 'Козлов'];
            $short = $shortNames[$index] ?? 'Дистрибьютор '.($index + 1);

            $user = User::create([
                'name' => $short,
                'email' => $email,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);
            $user->roles()->attach($role->id, [
                'company_name' => $short,
                'company_region' => 'Москва',
            ]);
        } else {
            $role = Role::findBySlug(Role::SLUG_DISTRIBUTOR);
            if ($role) {
                $user->roles()->syncWithoutDetaching([
                    $role->id => ['company_region' => 'Москва'],
                ]);
            }
        }

        $profile = $user->distributorProfile ?? $user->getOrCreateDistributorProfile();
        $profile->regions()->sync([$moscowId => ['is_primary' => true]]);

        return $profile;
    }

    private function ensureEndCompanyBuyerRegion(int $moscowId): void
    {
        $user = User::query()->where('email', 'company@test.com')->first();
        if ($user === null) {
            return;
        }

        $role = Role::findBySlug(Role::SLUG_END_COMPANY);
        if ($role) {
            $user->roles()->updateExistingPivot($role->id, ['company_region' => 'Москва']);
        }

        $profile = $user->getOrCreateEndCompanyProfile();
        $profile->deliveryAddresses()->updateOrCreate(
            ['name' => 'Основной адрес'],
            [
                'address' => 'г. Москва, ул. Тестовая, 1',
                'region_id' => $moscowId,
                'is_default' => true,
            ],
        );
    }
}
