<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
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
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Estado para crear el primer usuario fijo
     */
    public function primerUsuario(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'sebastian ayala',
            'email' => 'sebastian.ayala@leadsya.com',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('Temp.1122'),
            'remember_token' => Str::random(10),
        ]);
    }

    /**
     * Indicar que el correo no está verificado
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
