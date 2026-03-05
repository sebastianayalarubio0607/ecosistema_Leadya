<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Agrega la columna (ajusta "after" si quieres ubicarla en otra parte)
            $table->unsignedBigInteger('ad_id')->nullable()->after('meta_id_ad');

            // Índice para consultas
            $table->index('ad_id');

            // FK a meta_ads.id (si borran el ad, el lead queda con ad_id null)
            $table->foreign('ad_id')
                ->references('id')
                ->on('meta_ads')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Primero eliminar FK y luego columna
            $table->dropForeign(['ad_id']);
            $table->dropIndex(['ad_id']);
            $table->dropColumn('ad_id');
        });
    }
};
