<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('funnels', function (Blueprint $table) {
            $table->foreignId('meta_event_id')
                ->nullable()
                ->after('status')
                ->constrained('meta_events')
                ->nullOnDelete();

            $table->index('meta_event_id');
        });
    }

    public function down(): void
    {
        Schema::table('funnels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('meta_event_id');
        });
    }
};
