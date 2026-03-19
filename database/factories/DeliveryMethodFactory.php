<?php

namespace Database\Factories;

use App\Models\DeliveryMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryMethod>
 */
class DeliveryMethodFactory extends Factory
{
    protected $model = DeliveryMethod::class;

    private static array $names = [
        'Самовывоз',
        'Доставка транспортной компанией',
        'Доставка собственным транспортом',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->unique()->slug(1);

        return [
            'name' => fake()->randomElement(self::$names),
            'slug' => $slug,
            'description' => fake('ru_RU')->optional(0.7)->sentence(),
            'requires_tracking' => fake()->boolean(30),
            'sort_order' => fake()->numberBetween(1, 50),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
