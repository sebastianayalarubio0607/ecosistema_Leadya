<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'ad_id')) return;

            // Si existe FK, hay que tumbarla primero
            try { $table->dropForeign(['ad_id']); } catch (\Throwable $e) {}
            try { $table->dropIndex(['ad_id']); } catch (\Throwable $e) {}

            $table->dropColumn('ad_id');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedBigInteger('ad_id')->nullable()->after('meta_id_ad');
            $table->index('ad_id');

            $table->foreign('ad_id')
                ->references('id')
                ->on('meta_ads')
                ->nullOnDelete();
        });
    }
};
