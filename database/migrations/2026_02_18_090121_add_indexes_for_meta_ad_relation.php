<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Índice en leads.meta_id_ad
        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'meta_id_ad')) return;
            $table->index('meta_id_ad', 'leads_meta_id_ad_idx');
        });

        // Índice en meta_ads.meta_ad_id
        Schema::table('meta_ads', function (Blueprint $table) {
            if (!Schema::hasColumn('meta_ads', 'meta_ad_id')) return;
            $table->index('meta_ad_id', 'meta_ads_meta_ad_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_meta_id_ad_idx');
        });

        Schema::table('meta_ads', function (Blueprint $table) {
            $table->dropIndex('meta_ads_meta_ad_id_idx');
        });
    }
};
