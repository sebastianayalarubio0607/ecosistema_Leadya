<?php

use App\Models\Integrationtype;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('integrationtypes')) {
            return;
        }

        Integrationtype::firstOrCreate(
            ['name' => 'zapnito invitacion'],
            [
                'description' => 'Invitaciones de usuarios a Zapnito',
                'status' => 1,
            ]
        );
    }

    public function down(): void
    {
        // No se elimina el tipo para no afectar integraciones ya configuradas.
    }
};
