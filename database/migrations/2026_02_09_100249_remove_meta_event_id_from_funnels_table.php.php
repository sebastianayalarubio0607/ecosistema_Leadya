<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('funnels', 'meta_event_id')) {
            Schema::table('funnels', function (Blueprint $table) {
                // si existe la FK
                try {
                    $table->dropForeign(['meta_event_id']);
                } catch (\Throwable $e) {
                    // si por algún motivo no existe la FK, seguimos
                }

                $table->dropColumn('meta_event_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('funnels', function (Blueprint $table) {
            $table->foreignId('meta_event_id')
                ->nullable()
                ->after('status')
                ->constrained('meta_events')
                ->nullOnDelete();
        });
    }
};
