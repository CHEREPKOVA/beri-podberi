<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductDocument>
 */
class ProductDocumentFactory extends Factory
{
    protected $model = ProductDocument::class;

    private static array $names = [
        'Сертификат соответствия',
        'Инструкция по эксплуатации',
        'Технический паспорт',
        'Паспорт безопасности',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $originalName = fake()->randomElement(['certificate.pdf', 'instruction.pdf', 'datasheet.pdf']);
        $filePath = 'products/documents/' . fake()->uuid() . '.pdf';

        return [
            'product_id' => Product::factory(),
            'name' => fake()->randomElement(self::$names),
            'type' => fake()->randomElement([
                ProductDocument::TYPE_CERTIFICATE,
                ProductDocument::TYPE_INSTRUCTION,
                ProductDocument::TYPE_DATASHEET,
                ProductDocument::TYPE_OTHER,
            ]),
            'file_path' => $filePath,
            'original_name' => $originalName,
            'mime_type' => 'application/pdf',
            'file_size' => fake()->optional(0.8)->numberBetween(10000, 2000000),
            'valid_until' => fake()->optional(0.5)->dateTimeBetween('+1 month', '+2 years'),
            'notes' => fake('ru_RU')->optional(0.3)->sentence(),
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => ['product_id' => $product->id]);
    }
}
