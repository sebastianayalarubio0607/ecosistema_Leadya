<?php

use App\Models\Integrationtype;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Integrationtype::firstOrCreate(
            ['name' => 'GoHighLevel-Oportunidad'],
            [
                'description' => 'Crea o actualiza contactos y crea oportunidades en GoHighLevel',
                'status' => 1,
            ]
        );
    }

    public function down(): void
    {
        // No se elimina el tipo para no borrar integraciones existentes por cascada.
    }
};
