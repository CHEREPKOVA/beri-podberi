<?php

namespace Database\Seeders;

use App\Models\DeliveryMethod;
use App\Models\ManufacturerContact;
use App\Models\ManufacturerDocument;
use App\Models\ManufacturerProfile;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\ProductRegionalPrice;
use App\Models\ProductStock;
use App\Models\Region;
use App\Models\Role;
use App\Models\TransportCompany;
use App\Models\UnitType;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Создаёт тестовые данные для каталога автоаксессуаров (аккумуляторы, зарядные устройства, масла и жидкости).
 * Всё на русском. Запускать после основных сидеров.
 */
class FactoryDemoSeeder extends Seeder
{
    private static array $productNamesByCategory = [
        'akkumulyatory' => [
            'Аккумулятор 12В 55 А·ч прямая полярность',
            'Аккумулятор 12В 60 А·ч обратная полярность',
            'Аккумулятор 12В 75 А·ч для кроссоверов',
            'Аккумулятор AGM 12В 70 А·ч',
            'Аккумулятор 12В 100 А·ч для грузовика',
        ],
        'zaryadnye-ustrojstva-dlya-avto' => [
            'Зарядное устройство автоматическое 12В 8А',
            'Пуско-зарядное устройство 12/24В 20А',
            'Зарядное устройство для мото 12В 2А',
            'Кабель прикуривания 3 м',
            'Держатель клемм аккумулятора',
        ],
        'masla-i-zhidkosti' => [
            'Моторное масло 5W-40 синтетика 4 л',
            'Моторное масло 10W-40 полусинтетика 5 л',
            'Трансмиссионное масло 75W-90 1 л',
            'Тормозная жидкость DOT 4 0.5 л',
            'Охлаждающая жидкость антифриз -40°C 5 л',
            'Омыватель стекла зимний -30°C 2 л',
        ],
    ];

    public function run(): void
    {
        $regions = Region::take(5)->pluck('id');
        if ($regions->isEmpty()) {
            $this->command->warn('Нет регионов. Сначала выполните RegionSeeder.');
            return;
        }

        $categoryRoots = ProductCategory::whereNull('parent_id')->orderBy('sort_order')->get();
        if ($categoryRoots->isEmpty()) {
            $this->command->warn('Нет категорий. Сначала выполните ProductCategorySeeder.');
            return;
        }

        $categoriesWithChildren = ProductCategory::whereNotNull('parent_id')->pluck('id');
        $unitTypes = UnitType::pluck('id');
        $deliveryMethods = DeliveryMethod::pluck('id');
        $transportCompanies = TransportCompany::pluck('id');

        // Производитель 1: полный профиль, аккумуляторы и зарядные устройства
        $user1 = User::factory()
            ->withRoles(Role::SLUG_MANUFACTURER)
            ->create([
                'name' => 'ООО АккумТрейд',
                'email' => 'akkum-treid@test.com',
            ]);

        $profile1 = ManufacturerProfile::factory()
            ->forUser($user1)
            ->create([
                'full_name' => 'ООО «АккумТрейд»',
                'short_name' => 'АккумТрейд',
                'inn' => '7700000001',
                'description' => 'Поставка автомобильных аккумуляторов и зарядных устройств. Работаем с дистрибьюторами по всей России.',
            ]);

        $user1->roles()->updateExistingPivot(
            $user1->roles()->first()->id,
            ['company_name' => $profile1->short_name]
        );

        ManufacturerContact::factory()
            ->for($profile1, 'profile')
            ->primary()
            ->create([
                'full_name' => 'Иванов Иван Петрович',
                'position' => 'Менеджер по продажам',
                'email' => 'sales@akkum-treid.test',
            ]);
        ManufacturerContact::factory()->count(2)->for($profile1, 'profile')->create();

        $warehouses1 = [];
        foreach ($regions->take(2) as $regionId) {
            $warehouses1[] = Warehouse::factory()
                ->for($profile1, 'profile')
                ->create(['region_id' => $regionId, 'type' => Warehouse::TYPE_MAIN]);
        }

        ManufacturerDocument::factory()->count(2)->for($profile1, 'profile')->create();

        $profile1->regions()->attach($regions->take(2)->toArray(), ['is_primary' => false]);
        $profile1->regions()->updateExistingPivot($regions->first(), ['is_primary' => true]);

        if ($deliveryMethods->isNotEmpty()) {
            $profile1->deliveryMethods()->attach(
                $deliveryMethods->take(2)->mapWithKeys(fn ($id) => [$id => ['is_active' => true]])->toArray()
            );
        }
        if ($transportCompanies->isNotEmpty()) {
            $profile1->transportCompanies()->attach(
                $transportCompanies->take(2)->mapWithKeys(fn ($id) => [$id => ['is_active' => true]])->toArray()
            );
        }

        // Товары производителя 1: аккумуляторы и зарядные устройства
        $catBatteries = $categoryRoots->firstWhere('slug', 'akkumulyatory');
        $catChargers = $categoryRoots->firstWhere('slug', 'zaryadnye-ustrojstva-dlya-avto');
        $cats1 = collect([$catBatteries, $catChargers])->filter()->pluck('id');
        if ($cats1->isEmpty()) {
            $cats1 = $categoriesWithChildren->take(3);
        }

        $namesPool1 = array_merge(
            self::$productNamesByCategory['akkumulyatory'] ?? [],
            self::$productNamesByCategory['zaryadnye-ustrojstva-dlya-avto'] ?? []
        );
        for ($i = 0; $i < 5; $i++) {
            $categoryId = $cats1->isNotEmpty() ? $cats1->random() : $categoriesWithChildren->random();
            $product = Product::factory()
                ->forManufacturer($profile1)
                ->forCategory(ProductCategory::find($categoryId))
                ->create([
                    'name' => $namesPool1[$i % count($namesPool1)] ?? 'Товар ' . ($i + 1),
                    'unit_type_id' => $unitTypes->random(),
                    'status' => $i < 3 ? Product::STATUS_ACTIVE : Product::STATUS_DRAFT,
                    'show_in_catalog' => $i < 2,
                ]);
            ProductImage::factory()->for($product)->count(2)->create();
            ProductImage::factory()->for($product)->primary()->create();
            foreach ($warehouses1 as $warehouse) {
                ProductStock::factory()
                    ->forProduct($product)
                    ->forWarehouse($warehouse)
                    ->inStock()
                    ->create();
            }
            foreach ($regions->take(2) as $regionId) {
                ProductRegionalPrice::factory()
                    ->forProduct($product)
                    ->create(['region_id' => $regionId]);
            }
        }

        // Производитель 2: масла и жидкости
        $user2 = User::factory()
            ->withRoles(Role::SLUG_MANUFACTURER)
            ->create([
                'name' => 'ООО Масла и техжидкости',
                'email' => 'masla@test.com',
            ]);

        $profile2 = ManufacturerProfile::factory()
            ->forUser($user2)
            ->create([
                'full_name' => 'ООО «Масла и техжидкости»',
                'short_name' => 'Масла и техжидкости',
                'legal_form' => ManufacturerProfile::LEGAL_FORM_OOO,
                'inn' => '7700000002',
                'kpp' => '770201001',
                'description' => 'Моторные и трансмиссионные масла, тормозная и охлаждающая жидкости для авто.',
            ]);

        $user2->roles()->updateExistingPivot(
            $user2->roles()->first()->id,
            ['company_name' => $profile2->short_name]
        );

        ManufacturerContact::factory()->for($profile2, 'profile')->primary()->create([
            'full_name' => 'Петрова Мария Сергеевна',
            'position' => 'Руководитель отдела продаж',
        ]);
        $warehouse2 = Warehouse::factory()->for($profile2, 'profile')->create([
            'region_id' => $regions->first(),
            'type' => Warehouse::TYPE_MAIN,
        ]);
        $profile2->regions()->attach($regions->first(), ['is_primary' => true]);

        $catOils = $categoryRoots->firstWhere('slug', 'masla-i-zhidkosti');
        $catOilsId = $catOils?->id ?? $categoriesWithChildren->first();
        $namesPool2 = self::$productNamesByCategory['masla-i-zhidkosti'] ?? ['Моторное масло 5W-40 4 л', 'Тормозная жидкость DOT 4 0.5 л'];

        foreach (array_slice($namesPool2, 0, 2) as $productName) {
            $product = Product::factory()
                ->forManufacturer($profile2)
                ->forCategory(ProductCategory::find($catOilsId))
                ->active()
                ->create([
                    'name' => $productName,
                    'unit_type_id' => $unitTypes->first(),
                ]);
            ProductStock::factory()->forProduct($product)->forWarehouse($warehouse2)->inStock()->create();
            ProductRegionalPrice::factory()->forProduct($product)->create(['region_id' => $regions->first()]);
        }

        // Дополнительно создаём случайные товары до 100+ всего
        $allNames = array_merge(
            self::$productNamesByCategory['akkumulyatory'] ?? [],
            self::$productNamesByCategory['zaryadnye-ustrojstva-dlya-avto'] ?? [],
            self::$productNamesByCategory['masla-i-zhidkosti'] ?? []
        );
        $profiles = [$profile1, $profile2];
        $warehousesByProfile = [$profile1->id => $warehouses1, $profile2->id => [$warehouse2]];
        $targetTotal = 100;
        $createdSoFar = 7;
        $toCreate = $targetTotal - $createdSoFar;

        for ($i = 0; $i < $toCreate; $i++) {
            $profile = $profiles[$i % 2];
            $warehouses = $warehousesByProfile[$profile->id];
            $categoryId = $categoriesWithChildren->random();
            $name = $allNames[array_rand($allNames)];
            $status = fake()->randomElement([Product::STATUS_ACTIVE, Product::STATUS_ACTIVE, Product::STATUS_ACTIVE, Product::STATUS_DRAFT]);
            $showInCatalog = $status === Product::STATUS_ACTIVE && fake()->boolean(70);

            $product = Product::factory()
                ->forManufacturer($profile)
                ->forCategory(ProductCategory::find($categoryId))
                ->create([
                    'name' => $name . ' ' . fake()->optional(0.4)->numerify(' №##'),
                    'unit_type_id' => $unitTypes->random(),
                    'status' => $status,
                    'show_in_catalog' => $showInCatalog,
                    'published_at' => $status === Product::STATUS_ACTIVE ? now() : null,
                ]);
            ProductImage::factory()->for($product)->count(fake()->numberBetween(1, 2))->create();
            ProductImage::factory()->for($product)->primary()->create();
            foreach ($warehouses as $warehouse) {
                ProductStock::factory()
                    ->forProduct($product)
                    ->forWarehouse($warehouse)
                    ->inStock()
                    ->create();
            }
            foreach ($regions->take(2) as $regionId) {
                ProductRegionalPrice::factory()
                    ->forProduct($product)
                    ->create(['region_id' => $regionId]);
            }
        }

        $totalProducts = Product::count();
        $this->command->info("FactoryDemoSeeder: созданы 2 производителя и {$totalProducts} товаров (аккумуляторы, зарядные устройства, масла и жидкости).");
    }
}
