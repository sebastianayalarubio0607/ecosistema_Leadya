<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\IntegrationType>
 */
class IntegrationTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'description' => $this->faker->optional()->sentence(),
            'status' => $this->faker->boolean(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Estado para el tipo de integración "google_sheets"
     */
    public function googleSheets(): static
    {
        return $this->state(fn () => [
            'name' => 'google_sheets',
            'description' => null,
            'status' => 1,
            'created_at' => '2025-09-18 19:32:39',
            'updated_at' => '2025-09-18 19:33:02',
        ]);
    }
}
