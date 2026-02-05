<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $table = 'meta_ad_insights';

        if (!Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($table) {
            if (!Schema::hasColumn($table, 'outbound_clicks')) {
                $t->json('outbound_clicks')->nullable()->after('inline_link_clicks');
            }
            if (!Schema::hasColumn($table, 'ctr')) {
                $t->string('ctr', 50)->nullable()->after('outbound_clicks');
            }
            if (!Schema::hasColumn($table, 'unique_ctr')) {
                $t->string('unique_ctr', 50)->nullable()->after('ctr');
            }
            if (!Schema::hasColumn($table, 'cpc')) {
                $t->string('cpc', 50)->nullable()->after('unique_ctr');
            }
            if (!Schema::hasColumn($table, 'purchase_roas')) {
                $t->json('purchase_roas')->nullable()->after('cpm');
            }
            if (!Schema::hasColumn($table, 'raw')) {
                $t->json('raw')->nullable()->after('purchase_roas');
            }
        });
    }

    public function down(): void
    {
        $table = 'meta_ad_insights';

        if (!Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($table) {
            foreach (['outbound_clicks','ctr','unique_ctr','cpc','purchase_roas','raw'] as $col) {
                if (Schema::hasColumn($table, $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
