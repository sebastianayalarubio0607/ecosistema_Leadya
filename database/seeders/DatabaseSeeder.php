<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Integration;
use App\Models\Customer;
use App\Models\Integrationtype;
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

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@test.com',
        ]);





        // Crear clientes
        $customers = Customer::factory()->leadsYa()->create();

        //  // Crear tipos de integración
       // $types = Integrationtype::factory()->googleSheets()->create();

        // Crear integraciones ligadas a clientes y tipos
      // $integrations = Integration::factory()->googleSheetsPrueba()->create();
        
    }
}

