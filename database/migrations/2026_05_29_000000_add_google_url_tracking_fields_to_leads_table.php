<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'page_url')) {
                $table->mediumText('page_url')->nullable()->change();
            }

            $afterColumn = Schema::hasColumn('leads', 'gad_campaignid')
                ? 'gad_campaignid'
                : (Schema::hasColumn('leads', 'campaign_origin') ? 'campaign_origin' : null);

            foreach ([
                'google_ad_id',
                'google_adgroup_id',
                'google_campaign_id',
                'matchtype',
                'device',
            ] as $column) {
                if (! Schema::hasColumn('leads', $column)) {
                    $definition = $table->string($column)->nullable();

                    if ($afterColumn) {
                        $definition->after($afterColumn);
                    }

                    $afterColumn = $column;
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            foreach ([
                'device',
                'matchtype',
                'google_campaign_id',
                'google_adgroup_id',
                'google_ad_id',
            ] as $column) {
                if (Schema::hasColumn('leads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
