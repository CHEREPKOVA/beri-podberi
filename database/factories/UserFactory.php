<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Привязать роль (или несколько) к пользователю после создания.
     *
     * @param  string|array<int, string>  $slugs  Слаг роли или массив слагов
     */
    public function withRoles(string|array $slugs): static
    {
        $slugs = is_array($slugs) ? $slugs : [$slugs];
        return $this->afterCreating(function (User $user) use ($slugs): void {
            $roleIds = Role::whereIn('slug', $slugs)->pluck('id');
            $user->roles()->sync($roleIds);
        });
    }
}
