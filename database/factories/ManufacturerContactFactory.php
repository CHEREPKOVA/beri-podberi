<?php

namespace Database\Factories;

use App\Models\ManufacturerContact;
use App\Models\ManufacturerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManufacturerContact>
 */
class ManufacturerContactFactory extends Factory
{
    protected $model = ManufacturerContact::class;

    private static array $positions = [
        'Менеджер по продажам',
        'Руководитель отдела продаж',
        'Специалист по логистике',
        'Главный бухгалтер',
        'Коммерческий директор',
    ];

    private static array $departments = [
        'Отдел продаж',
        'Логистика',
        'Бухгалтерия',
        'Снабжение',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'manufacturer_profile_id' => ManufacturerProfile::factory(),
            'full_name' => fake('ru_RU')->name(),
            'position' => fake()->randomElement(self::$positions),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('+7 (9##) ###-##-##'),
            'is_primary' => false,
            'department' => fake()->optional(0.5)->randomElement(self::$departments),
            'notes' => fake('ru_RU')->optional(0.3)->sentence(),
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => ['is_primary' => true]);
    }
}
