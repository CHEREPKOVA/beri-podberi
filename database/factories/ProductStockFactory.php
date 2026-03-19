<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductStock>
 */
class ProductStockFactory extends Factory
{
    protected $model = ProductStock::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(0, 1000);
        $reserved = fake()->numberBetween(0, min(100, $quantity));

        return [
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'quantity' => $quantity,
            'reserved' => $reserved,
            'stock_updated_at' => now(),
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => ['product_id' => $product->id]);
    }

    public function forWarehouse(Warehouse $warehouse): static
    {
        return $this->state(fn (array $attributes) => ['warehouse_id' => $warehouse->id]);
    }

    public function inStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => fake()->numberBetween(10, 500),
            'reserved' => 0,
        ]);
    }
}
