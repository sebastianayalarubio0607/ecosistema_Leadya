<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // TEXT soporta más de 10k sin problema
            if (Schema::hasColumn('leads', 'page_url')) {
                $table->text('page_url')->nullable()->change();
            }

            if (Schema::hasColumn('leads', 'agent')) {
                $table->text('agent')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // si antes eran varchar(255), vuelve a 255 (ajusta si tú tenías otro tamaño)
            if (Schema::hasColumn('leads', 'page_url')) {
                $table->string('page_url', 255)->nullable()->change();
            }

            if (Schema::hasColumn('leads', 'agent')) {
                $table->string('agent', 255)->nullable()->change();
            }
        });
    }
};
