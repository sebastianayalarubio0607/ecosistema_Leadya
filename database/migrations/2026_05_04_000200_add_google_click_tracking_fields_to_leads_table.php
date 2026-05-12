<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $afterColumn = Schema::hasColumn('leads', 'g_clid') ? 'g_clid' : 'campaign_origin';

            if (! Schema::hasColumn('leads', 'gclid')) {
                $table->string('gclid')->nullable()->after($afterColumn);
                $afterColumn = 'gclid';
            }

            if (! Schema::hasColumn('leads', 'gbraid')) {
                $table->string('gbraid')->nullable()->after($afterColumn);
                $afterColumn = 'gbraid';
            }

            if (! Schema::hasColumn('leads', 'wbraid')) {
                $table->string('wbraid')->nullable()->after($afterColumn);
                $afterColumn = 'wbraid';
            }

            if (! Schema::hasColumn('leads', 'gad_source')) {
                $table->string('gad_source')->nullable()->after($afterColumn);
                $afterColumn = 'gad_source';
            }

            if (! Schema::hasColumn('leads', 'gad_campaignid')) {
                $table->string('gad_campaignid')->nullable()->after($afterColumn);
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            foreach (['gad_campaignid', 'gad_source', 'wbraid', 'gbraid', 'gclid'] as $column) {
                if (Schema::hasColumn('leads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
