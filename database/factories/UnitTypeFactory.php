<?php

namespace Database\Factories;

use App\Models\UnitType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitType>
 */
class UnitTypeFactory extends Factory
{
    protected $model = UnitType::class;

    private static array $units = [
        ['name' => 'Штука', 'short' => 'шт.', 'code' => 'pcs'],
        ['name' => 'Литр', 'short' => 'л', 'code' => 'l'],
        ['name' => 'Миллилитр', 'short' => 'мл', 'code' => 'ml'],
        ['name' => 'Упаковка', 'short' => 'уп.', 'code' => 'pack'],
        ['name' => 'Коробка', 'short' => 'кор.', 'code' => 'box'],
        ['name' => 'Канистра', 'short' => 'кан.', 'code' => 'can'],
        ['name' => 'Комплект', 'short' => 'компл.', 'code' => 'set'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unit = fake()->randomElement(self::$units);

        return [
            'name' => $unit['name'],
            'short_name' => $unit['short'],
            'code' => $unit['code'].'-'.fake()->unique()->numberBetween(100, 999),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
