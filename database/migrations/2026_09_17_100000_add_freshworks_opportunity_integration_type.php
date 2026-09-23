<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('integrationtypes') || DB::table('integrationtypes')->where('name', 'Freshworks-Oportunidad')->exists()) {
            return;
        }

        DB::table('integrationtypes')->insert([
            'name' => 'Freshworks-Oportunidad',
            'description' => 'Crea contactos y oportunidades de Freshworks con sincronización de stages.',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Intentionally no-op: deleting a type could orphan user-created integrations.
    }
};
