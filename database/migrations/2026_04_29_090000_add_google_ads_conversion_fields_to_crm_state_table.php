<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_state', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_state', 'google_ads_conversion_action_id')) {
                $table->string('google_ads_conversion_action_id', 64)->nullable()->after('unmanaged');
            }

            if (! Schema::hasColumn('crm_state', 'google_ads_conversion_action_name')) {
                $table->string('google_ads_conversion_action_name')->nullable()->after('google_ads_conversion_action_id');
            }

            if (! Schema::hasColumn('crm_state', 'google_ads_conversion_action_resource_name')) {
                $table->string('google_ads_conversion_action_resource_name')->nullable()->after('google_ads_conversion_action_name');
            }

            if (! Schema::hasColumn('crm_state', 'google_ads_conversion_enabled')) {
                $table->boolean('google_ads_conversion_enabled')->default(false)->index()->after('google_ads_conversion_action_resource_name');
            }

            if (! Schema::hasColumn('crm_state', 'google_ads_conversion_value')) {
                $table->decimal('google_ads_conversion_value', 16, 2)->nullable()->after('google_ads_conversion_enabled');
            }

            if (! Schema::hasColumn('crm_state', 'google_ads_conversion_currency')) {
                $table->string('google_ads_conversion_currency', 3)->default('COP')->after('google_ads_conversion_value');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_state', function (Blueprint $table) {
            if (Schema::hasColumn('crm_state', 'google_ads_conversion_enabled')) {
                $table->dropIndex(['google_ads_conversion_enabled']);
            }

            foreach ([
                'google_ads_conversion_currency',
                'google_ads_conversion_value',
                'google_ads_conversion_enabled',
                'google_ads_conversion_action_resource_name',
                'google_ads_conversion_action_name',
                'google_ads_conversion_action_id',
            ] as $column) {
                if (Schema::hasColumn('crm_state', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
