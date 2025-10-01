<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Integration;
use App\Models\Integrationtype;
use App\Models\Customer;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Integration>
 */
class IntegrationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true), // Ej: "Google Sheets"
            'description' => $this->faker->optional()->sentence(),
            'integrationtype_id' => Integrationtype::factory(), // Relación con factory de IntegrationType
            'status' => $this->faker->boolean(),
            'customer_id' => Customer::factory(), // Relación con factory de Customer
            'url' => $this->faker->optional()->url(),
            'tokent' => $this->faker->optional()->sha256(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Estado para generar el registro específico del INSERT
     */
    public function googleSheetsPrueba(): static
    {
        return $this->state(fn () => [
            'name' => 'google_sheets prueba',
            'description' => 'prueba',
            'integrationtype_id' => 1, // debe existir en la BD
            'status' => 1,
            'customer_id' => 1, // debe existir en la BD
            'url' => 'https://script.google.com/macros/s/AKfycbzmFZUxTWwBGWUEO-c7KpjUpmANZnYJOWFyyEDaXS5vscmxMYHSt5kR3rHoFd0uJdcv/exec',
            'tokent' => null,
            'created_at' => '2025-09-19 00:35:45',
            'updated_at' => '2025-09-19 00:35:45',
        ]);
    }
}
