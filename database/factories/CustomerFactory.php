<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company(),  // Ejemplo: "Acme Corp"
            'description' => $this->faker->optional()->sentence(),
            // Si quieres siempre el mismo token, deja fijo como el insert
            // 'token' => '7a7c7a48cd30b7af2df415a4c0a102ef1195d95dc1af9acffc55af5a722e80a0',
            // O si prefieres simular:
            'token' => hash('sha256', $this->faker->uuid()),

            'status' => $this->faker->boolean(),
            'fb_pixel_id' => $this->faker->optional()->regexify('[0-9]{15,16}'),
            'fb_access_token' => $this->faker->optional()->sha256(),
            'fb_test_event_code' => $this->faker->optional()->lexify('TEST????'),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Estado para generar un cliente específico "LeadsYa"
     */
    public function leadsYa(): static
    {
        return $this->state(fn () => [
            'name' => 'LeadsYa',
            'fb_pixel_id' =>2336988119939951,
            'fb_access_token' => 'EAAQplnNshE4BPoWrjFIwjYzLrCPLCdaeyLAG1MeqeSymzxojlkr6g89Bpnbgr0iOs2nYa6ZC31ddjNpvnSIFMI3kjn6ZBtUZChx19rmtjyDwPk1ce4MlINCQBpcsBoalf1fmZAVAm70JZBIzZCJkErp1GoiUm2BZC2XEOizjzPLHMcLVSBUrLblGbWMa7AvugZDZD',
            'description' => 'LeadsYa',
            'token' => hash('sha256', 'Temp.1122'),
            'status' => 1,
            'created_at' => '2025-05-04 08:08:31',
            'updated_at' => '2025-09-18 02:25:00',
        ]);
    }
    
}
