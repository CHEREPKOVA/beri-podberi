<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductRegionalPrice;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductRegionalPrice>
 */
class ProductRegionalPriceFactory extends Factory
{
    protected $model = ProductRegionalPrice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'region_id' => Region::factory(),
            'price' => fake()->randomFloat(2, 50, 5000),
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => ['product_id' => $product->id]);
    }

    public function forRegion(Region $region): static
    {
        return $this->state(fn (array $attributes) => ['region_id' => $region->id]);
    }
}
