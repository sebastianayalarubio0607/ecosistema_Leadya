<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $table = 'meta_ad_insights';

        if (!Schema::hasTable($table)) return;

        Schema::table($table, function (Blueprint $t) use ($table) {
            if (!Schema::hasColumn($table, 'ctr')) {
                $t->decimal('ctr', 12, 6)->nullable()->after('inline_link_clicks');
            }
            if (!Schema::hasColumn($table, 'unique_ctr')) {
                $t->decimal('unique_ctr', 12, 6)->nullable()->after('ctr');
            }
            if (!Schema::hasColumn($table, 'cpc')) {
                $t->decimal('cpc', 12, 4)->nullable()->after('unique_ctr');
            }
        });
    }

    public function down(): void
    {
        $table = 'meta_ad_insights';

        if (!Schema::hasTable($table)) return;

        Schema::table($table, function (Blueprint $t) use ($table) {
            foreach (['ctr', 'unique_ctr', 'cpc'] as $col) {
                if (Schema::hasColumn($table, $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
