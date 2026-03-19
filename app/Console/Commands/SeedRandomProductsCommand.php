<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\ProductRegionalPrice;
use App\Models\ProductStock;
use App\Models\ManufacturerProfile;
use App\Models\Region;
use App\Models\UnitType;
use App\Models\Warehouse;
use Illuminate\Console\Command;

class SeedRandomProductsCommand extends Command
{
    protected $signature = 'products:seed-random {count=100 : Количество товаров для создания}';

    protected $description = 'Создаёт указанное количество случайных товаров у существующих производителей (по умолчанию 100)';

    private static array $productNames = [
        'Аккумулятор 12В 55 А·ч прямая полярность',
        'Аккумулятор 12В 60 А·ч обратная полярность',
        'Аккумулятор 12В 75 А·ч для кроссоверов',
        'Аккумулятор AGM 12В 70 А·ч',
        'Аккумулятор 12В 100 А·ч для грузовика',
        'Зарядное устройство автоматическое 12В 8А',
        'Пуско-зарядное устройство 12/24В 20А',
        'Зарядное устройство для мото 12В 2А',
        'Кабель прикуривания 3 м',
        'Моторное масло 5W-40 синтетика 4 л',
        'Моторное масло 10W-40 полусинтетика 5 л',
        'Трансмиссионное масло 75W-90 1 л',
        'Тормозная жидкость DOT 4 0.5 л',
        'Охлаждающая жидкость антифриз -40°C 5 л',
        'Омыватель стекла зимний -30°C 2 л',
    ];

    public function handle(): int
    {
        $count = (int) $this->argument('count');
        if ($count < 1) {
            $this->error('Укажите число больше 0.');
            return self::FAILURE;
        }

        $profiles = ManufacturerProfile::with('warehouses')->get();
        if ($profiles->isEmpty()) {
            $this->error('Нет производителей. Сначала выполните сидер с производителями (например FactoryDemoSeeder после migrate:fresh --seed).');
            return self::FAILURE;
        }

        $categories = ProductCategory::whereNotNull('parent_id')->orWhereHas('parent')->pluck('id');
        if ($categories->isEmpty()) {
            $categories = ProductCategory::pluck('id');
        }
        if ($categories->isEmpty()) {
            $this->error('Нет категорий. Выполните ProductCategorySeeder.');
            return self::FAILURE;
        }

        $regions = Region::pluck('id');
        $unitTypes = UnitType::pluck('id');
        if ($unitTypes->isEmpty()) {
            $this->error('Нет единиц измерения. Выполните UnitTypeSeeder.');
            return self::FAILURE;
        }

        $this->info("Создаём {$count} случайных товаров...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        for ($i = 0; $i < $count; $i++) {
            $profile = $profiles->random();
            $warehouses = $profile->warehouses;
            if ($warehouses->isEmpty()) {
                $warehouse = Warehouse::factory()->for($profile, 'profile')->create([
                    'region_id' => $regions->first(),
                    'type' => Warehouse::TYPE_MAIN,
                ]);
                $warehouses = collect([$warehouse]);
            }

            $categoryId = $categories->random();
            $name = self::$productNames[array_rand(self::$productNames)];
            if (fake()->boolean(30)) {
                $name .= ' ' . fake()->numerify('№##');
            }
            $status = fake()->randomElement([Product::STATUS_ACTIVE, Product::STATUS_ACTIVE, Product::STATUS_DRAFT]);
            $showInCatalog = $status === Product::STATUS_ACTIVE && fake()->boolean(70);

            $product = Product::factory()
                ->forManufacturer($profile)
                ->forCategory(ProductCategory::find($categoryId))
                ->create([
                    'name' => $name,
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

            $regionIds = $regions->take(2);
            foreach ($regionIds as $regionId) {
                ProductRegionalPrice::factory()
                    ->forProduct($product)
                    ->create(['region_id' => $regionId]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Готово. Всего товаров в каталоге: " . Product::count());

        return self::SUCCESS;
    }
}
