<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'g_ad')) {
                $afterColumn = Schema::hasColumn('leads', 'meta_id_ad') ? 'meta_id_ad' : 'campaign_origin';
                $table->string('g_ad')->nullable()->after($afterColumn);
            }

            if (! Schema::hasColumn('leads', 'g_clid')) {
                $afterColumn = Schema::hasColumn('leads', 'g_ad')
                    ? 'g_ad'
                    : (Schema::hasColumn('leads', 'meta_id_ad') ? 'meta_id_ad' : 'campaign_origin');
                $table->string('g_clid')->nullable()->after($afterColumn);
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'g_clid')) {
                $table->dropColumn('g_clid');
            }

            if (Schema::hasColumn('leads', 'g_ad')) {
                $table->dropColumn('g_ad');
            }
        });
    }
};
