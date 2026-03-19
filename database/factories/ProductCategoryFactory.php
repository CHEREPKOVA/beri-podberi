<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    private static array $mainCategories = [
        'Аккумуляторы',
        'Зарядные устройства для авто',
        'Масла и жидкости',
    ];

    private static array $subCategories = [
        'Аккумуляторы для легковых автомобилей',
        'Аккумуляторы для грузовиков',
        'Зарядные устройства',
        'Пуско-зарядные устройства',
        'Моторные масла',
        'Трансмиссионные масла',
        'Тормозная жидкость',
        'Охлаждающая жидкость',
        'Жидкость омывателя',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(array_merge(self::$mainCategories, self::$subCategories));
        $slug = \Illuminate\Support\Str::slug($name) . '-' . fake()->unique()->numberBetween(1, 9999);

        return [
            'name' => $name,
            'slug' => $slug,
            'parent_id' => null,
            'description' => fake('ru_RU')->optional(0.5)->sentence(),
            'icon' => null,
            'sort_order' => fake()->numberBetween(1, 100),
            'is_active' => true,
        ];
    }

    public function childOf(?ProductCategory $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent?->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
