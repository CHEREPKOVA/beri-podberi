<?php

namespace Database\Factories;

use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Region>
 */
class RegionFactory extends Factory
{
    protected $model = Region::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $districts = Region::federalDistricts();
        $names = [
            'Московская область',
            'Ленинградская область',
            'Краснодарский край',
            'Свердловская область',
            'Нижегородская область',
            'Ростовская область',
            'Самарская область',
            'Республика Татарстан',
            'Красноярский край',
            'Воронежская область',
        ];
        $name = fake()->unique()->randomElement($names);

        return [
            'name' => $name,
            'code' => strtoupper(fake()->unique()->lexify('??')),
            'federal_district' => fake()->randomElement($districts),
            'sort_order' => fake()->numberBetween(1, 100),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
