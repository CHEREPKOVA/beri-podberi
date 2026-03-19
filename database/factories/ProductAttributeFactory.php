<?php

namespace Database\Factories;

use App\Models\ProductAttribute;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductAttribute>
 */
class ProductAttributeFactory extends Factory
{
    protected $model = ProductAttribute::class;

    private static array $names = [
        'Бренд',
        'Напряжение (В)',
        'Ёмкость (А·ч)',
        'Полярность',
        'Тип аккумулятора',
        'Тип масла',
        'Вязкость (SAE)',
        'Объём (л)',
        'Страна производства',
        'Гарантия (мес.)',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(self::$names);
        $type = fake()->randomElement([
            ProductAttribute::TYPE_TEXT,
            ProductAttribute::TYPE_NUMBER,
            ProductAttribute::TYPE_SELECT,
            ProductAttribute::TYPE_BOOLEAN,
        ]);
        $options = $type === ProductAttribute::TYPE_SELECT
            ? ['Вариант 1', 'Вариант 2', 'Вариант 3']
            : null;

        return [
            'product_category_id' => null,
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name) . '-' . fake()->unique()->numberBetween(1, 9999),
            'type' => $type,
            'options' => $options,
            'is_filterable' => fake()->boolean(40),
            'is_required' => fake()->boolean(20),
            'sort_order' => fake()->numberBetween(1, 50),
            'is_active' => true,
        ];
    }

    public function forCategory(?ProductCategory $category): static
    {
        return $this->state(fn (array $attributes) => [
            'product_category_id' => $category?->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
