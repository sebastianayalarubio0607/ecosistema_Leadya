<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('monday_boards') || Schema::hasColumn('monday_boards', 'is_default')) {
            return;
        }

        Schema::table('monday_boards', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('status');
            $table->index(['integration_id', 'is_default'], 'monday_boards_integration_default_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('monday_boards') || ! Schema::hasColumn('monday_boards', 'is_default')) {
            return;
        }

        Schema::table('monday_boards', function (Blueprint $table) {
            $table->dropIndex('monday_boards_integration_default_index');
            $table->dropColumn('is_default');
        });
    }
};
