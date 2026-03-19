<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductAttributeValue>
 */
class ProductAttributeValueFactory extends Factory
{
    protected $model = ProductAttributeValue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'product_attribute_id' => ProductAttribute::factory(),
            'value' => fake('ru_RU')->optional(0.8)->word() ?: (string) fake()->numberBetween(1, 100),
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => ['product_id' => $product->id]);
    }

    public function forAttribute(ProductAttribute $attribute): static
    {
        $value = match ($attribute->type) {
            'number' => (string) fake()->numberBetween(1, 1000),
            'boolean' => fake()->boolean() ? '1' : '0',
            'select' => fake()->randomElement($attribute->options ?? ['Да', 'Нет']),
            default => fake('ru_RU')->words(2, true),
        };

        return $this->state(fn (array $attributes) => [
            'product_attribute_id' => $attribute->id,
            'value' => $value,
        ]);
    }
}
