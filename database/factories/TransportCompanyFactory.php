<?php

namespace Database\Factories;

use App\Models\TransportCompany;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TransportCompany>
 */
class TransportCompanyFactory extends Factory
{
    protected $model = TransportCompany::class;

    private static array $names = [
        'СДЭК',
        'Деловые Линии',
        'ПЭК',
        'Байкал Сервис',
        'Энергия',
        'КИТ',
        'Boxberry',
        'Почта России',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(self::$names);
        $slug = Str::slug($name);

        return [
            'name' => $name,
            'slug' => $slug,
            'website' => fake()->optional(0.7)->url(),
            'tracking_url' => fake()->optional(0.5)->url().'/track/',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
