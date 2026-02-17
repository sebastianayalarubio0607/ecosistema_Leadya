<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qualification', function (Blueprint $table) {
            if (!Schema::hasColumn('qualification', 'funnel_id')) {
                $table->foreignId('funnel_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('funnels')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('qualification', function (Blueprint $table) {
            if (Schema::hasColumn('qualification', 'funnel_id')) {
                $table->dropConstrainedForeignId('funnel_id');
            }
        });
    }
};
