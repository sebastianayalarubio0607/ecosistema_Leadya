<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('crm_state', 'meta_event_id')) {
            Schema::table('crm_state', function (Blueprint $table) {
                $table->foreignId('meta_event_id')
                    ->nullable()
                    ->after('qualification')
                    ->constrained('meta_events')
                    ->nullOnDelete();

                $table->index('meta_event_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('crm_state', 'meta_event_id')) {
            Schema::table('crm_state', function (Blueprint $table) {
                $table->dropForeign(['meta_event_id']);
                $table->dropColumn('meta_event_id');
            });
        }
    }
};
