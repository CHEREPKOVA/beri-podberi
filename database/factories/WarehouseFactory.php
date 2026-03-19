<?php

namespace Database\Factories;

use App\Models\Region;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    private static array $names = [
        'Склад №1',
        'Основной склад',
        'Склад автозапчастей',
        'Склад готовой продукции',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'manufacturer_profile_id' => \App\Models\ManufacturerProfile::factory(),
            'name' => fake()->randomElement(self::$names),
            'address' => fake('ru_RU')->address(),
            'region_id' => Region::factory(),
            'type' => fake()->randomElement([
                Warehouse::TYPE_MAIN,
                Warehouse::TYPE_TEMPORARY,
                Warehouse::TYPE_TRANSIT,
            ]),
            'responsible_person' => fake('ru_RU')->name(),
            'phone' => fake()->numerify('+7 (9##) ###-##-##'),
            'notes' => fake('ru_RU')->optional(0.4)->sentence(),
            'working_hours' => 'Пн–Пт 9:00–18:00',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function main(): static
    {
        return $this->state(fn (array $attributes) => ['type' => Warehouse::TYPE_MAIN]);
    }
}
