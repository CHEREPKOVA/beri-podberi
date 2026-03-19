<?php

namespace Database\Factories;

use App\Models\ManufacturerDocument;
use App\Models\ManufacturerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManufacturerDocument>
 */
class ManufacturerDocumentFactory extends Factory
{
    protected $model = ManufacturerDocument::class;

    private static array $names = [
        'Свидетельство о регистрации',
        'Карточка предприятия',
        'Лицензия на деятельность',
        'Сертификат на продукцию',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $originalName = fake()->randomElement(['certificate.pdf', 'license.pdf', 'company_card.pdf']);
        $filePath = 'documents/' . fake()->uuid() . '.pdf';

        return [
            'manufacturer_profile_id' => ManufacturerProfile::factory(),
            'name' => fake()->randomElement(self::$names),
            'type' => fake()->randomElement([
                ManufacturerDocument::TYPE_REGISTRATION_CERTIFICATE,
                ManufacturerDocument::TYPE_COMPANY_CARD,
                ManufacturerDocument::TYPE_LICENSE,
                ManufacturerDocument::TYPE_OTHER,
            ]),
            'file_path' => $filePath,
            'original_name' => $originalName,
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(50000, 5000000),
            'valid_until' => fake()->optional(0.7)->dateTimeBetween('+1 month', '+2 years'),
            'notes' => fake('ru_RU')->optional(0.3)->sentence(),
        ];
    }
}
