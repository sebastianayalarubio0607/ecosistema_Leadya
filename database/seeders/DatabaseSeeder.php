<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Integration;
use App\Models\Customer;
use App\Models\IntegrationType;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $user = User::factory()->primerUsuario()->create();



        // Crear clientes
        $customers = Customer::factory()->leadsYa()->create();

        //  // Crear tipos de integración
        $types = IntegrationType::factory()->googleSheets()->create();
                //  // Crear tipos de integración
        $types = IntegrationType::factory()->kommo()->create();
                //  // Crear tipos de integración
        $types = IntegrationType::factory()->lety()->create();

        // Crear integraciones ligadas a clientes y tipos
        $integrations = Integration::factory()->googleSheetsPrueba()->create();
    }
}
