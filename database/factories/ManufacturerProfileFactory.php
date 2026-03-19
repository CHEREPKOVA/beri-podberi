<?php

namespace Database\Factories;

use App\Models\ManufacturerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManufacturerProfile>
 */
class ManufacturerProfileFactory extends Factory
{
    protected $model = ManufacturerProfile::class;

    private static array $companyNames = [
        'ООО «АккумТрейд»',
        'ООО «АвтоЭнерго»',
        'ИП Петров',
        'ООО «Масла и техжидкости»',
        'ООО «Заряд Сервис»',
        'ООО «Батарейка»',
        'ООО «АвтоМасла Плюс»',
        'ООО «ЭнергоАвто»',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fullName = fake()->randomElement(self::$companyNames);
        if (str_contains($fullName, '«')) {
            $shortName = preg_replace('/^[^»]+»\s*/u', '', $fullName);
            $shortName = preg_replace('/\s*\([^)]*\)\s*/u', '', $shortName);
        } else {
            $shortName = mb_substr($fullName, 0, 15);
        }

        return [
            'user_id' => User::factory(),
            'full_name' => $fullName,
            'short_name' => $shortName,
            'legal_form' => fake()->randomElement([
                ManufacturerProfile::LEGAL_FORM_OOO,
                ManufacturerProfile::LEGAL_FORM_IP,
                ManufacturerProfile::LEGAL_FORM_AO,
            ]),
            'inn' => (string) fake()->unique()->numerify('##########'),
            'kpp' => fake()->optional(0.8)->numerify('#########'),
            'ogrn' => fake()->optional(0.7)->numerify('###############'),
            'legal_address' => fake('ru_RU')->address(),
            'actual_address' => fake('ru_RU')->optional(0.8)->address(),
            'bank_name' => fake('ru_RU')->optional(0.6)->company() . ' банк',
            'bik' => fake()->optional(0.6)->numerify('########'),
            'checking_account' => fake()->optional(0.6)->numerify('####################'),
            'correspondent_account' => fake()->optional(0.5)->numerify('####################'),
            'logo' => null,
            'description' => fake('ru_RU')->optional(0.5)->realText(200),
            'delivery_notes' => fake('ru_RU')->optional(0.3)->sentence(),
            'locked_fields' => null,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => ['user_id' => $user->id]);
    }
}
