<?php

use App\Models\Integrationtype;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Integrationtype::firstOrCreate(
            ['name' => 'Markix'],
            ['description' => 'Envía leads a Markix y guarda el markix_id como ID CRM']
        );
    }

    public function down(): void
    {
        Integrationtype::where('name', 'Markix')->delete();
    }
};
