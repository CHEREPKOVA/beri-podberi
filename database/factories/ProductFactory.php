<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ManufacturerProfile;
use App\Models\UnitType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 * Товары каталога автоаксессуаров: аккумуляторы, зарядные устройства, масла и жидкости.
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    private static array $productNames = [
        'Аккумулятор 12В 60 А·ч прямая полярность',
        'Аккумулятор 12В 75 А·ч обратная полярность',
        'Аккумулятор 12В 100 А·ч для грузовика',
        'Аккумулятор AGM 12В 70 А·ч',
        'Зарядное устройство автоматическое 12В 8А',
        'Пуско-зарядное устройство 12/24В 20А',
        'Зарядное устройство для мотоаккумуляторов 12В',
        'Кабель прикуривания 3 м',
        'Моторное масло 5W-40 синтетика 4 л',
        'Моторное масло 10W-40 полусинтетика 5 л',
        'Трансмиссионное масло 75W-90 1 л',
        'Тормозная жидкость DOT 4 0.5 л',
        'Охлаждающая жидкость антифриз -40°C 5 л',
        'Омыватель стекла летний 2 л',
        'Омыватель стекла зимний -30°C 2 л',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(self::$productNames);
        $sku = 'AUTO-' . strtoupper(fake()->unique()->bothify('???-####'));

        return [
            'manufacturer_profile_id' => ManufacturerProfile::factory(),
            'category_id' => ProductCategory::factory(),
            'unit_type_id' => UnitType::factory(),
            'name' => $name,
            'sku' => $sku,
            'description' => fake('ru_RU')->optional(0.8)->realText(300),
            'video_url' => fake()->optional(0.2)->url(),
            'min_order_quantity' => fake()->optional(0.5)->numberBetween(1, 20),
            'base_price' => fake()->randomFloat(2, 500, 25000),
            'manufacturer_sku' => fake()->optional(0.6)->numerify('MF-####'),
            'distributor_sku' => null,
            'ean' => fake()->optional(0.4)->numerify('#############'),
            'barcode' => fake()->optional(0.4)->numerify('#############'),
            'expiry_date' => fake()->optional(0.2)->dateTimeBetween('+6 months', '+3 years'),
            'storage_conditions' => fake('ru_RU')->optional(0.4)->sentence(),
            'transport_conditions' => fake('ru_RU')->optional(0.3)->sentence(),
            'instruction_url' => null,
            'status' => Product::STATUS_DRAFT,
            'published_at' => null,
            'show_in_catalog' => false,
            'sync_source' => null,
            'synced_at' => null,
            'is_modified' => false,
            'price_updated_at' => now(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Product::STATUS_ACTIVE,
            'published_at' => now(),
            'show_in_catalog' => true,
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => ['status' => Product::STATUS_HIDDEN]);
    }

    public function forManufacturer(ManufacturerProfile $profile): static
    {
        return $this->state(fn (array $attributes) => [
            'manufacturer_profile_id' => $profile->id,
        ]);
    }

    public function forCategory(?ProductCategory $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category?->id,
        ]);
    }
}
