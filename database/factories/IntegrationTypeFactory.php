<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Integrationtype;

class IntegrationtypeFactory extends Factory
{
    // Tell Laravel which model this factory is for
    protected $model = Integrationtype::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'description' => $this->faker->optional()->sentence(),
            'status' => $this->faker->boolean(),
            // Usually you can omit timestamps; Eloquent fills them automatically.
            // 'created_at' => now(),
            // 'updated_at' => now(),
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
        public function kommo(): static
    {
        return $this->state(fn () => [
            'name' => 'kommo',
            'description' => null,
            'status' => 1,
            'created_at' => '2025-09-18 19:32:39',
            'updated_at' => '2025-09-18 19:33:02',
        ]);
    }
        public function lety(): static
    {
        return $this->state(fn () => [
            'name' => 'lety',
            'description' => null,
            'status' => 1,
            'created_at' => '2025-09-18 19:32:39',
            'updated_at' => '2025-09-18 19:33:02',
        ]);
    }
}

