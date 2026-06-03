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
            'filter_display_type' => null,
            'filter_values_source' => ProductAttribute::FILTER_VALUES_FIXED,
            'filter_allow_multiple' => fake()->boolean(30),
            'is_required' => fake()->boolean(20),
            'sort_order' => fake()->numberBetween(1, 50),
            'filter_sort_order' => null,
            'is_active' => true,
        ];
    }

    public function forCategory(?ProductCategory $category): static
    {
        return $this->state(fn (array $attributes) => [
            'product_category_id' => $category?->id,
        ])->afterCreating(function (ProductAttribute $attribute) use ($category): void {
            if ($category) {
                $attribute->syncCatalogCategories([$category->id]);
            }
        });
    }

    /**
     * @param  list<int>|list<ProductCategory>  $categories
     */
    public function forCategories(array $categories): static
    {
        $ids = collect($categories)->map(function ($item): int {
            return $item instanceof ProductCategory ? (int) $item->id : (int) $item;
        })->filter()->values()->all();

        return $this->state(fn (array $attributes) => [
            'product_category_id' => $ids[0] ?? null,
        ])->afterCreating(function (ProductAttribute $attribute) use ($ids): void {
            $attribute->syncCatalogCategories($ids);
        });
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
