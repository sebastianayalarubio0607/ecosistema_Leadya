<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'whasapp_user_id')) {
                $table->string('whasapp_user_id')->nullable()->after('meta_payload');
                $table->index('whasapp_user_id', 'leads_whasapp_user_id_idx');
            }

            if (! Schema::hasColumn('leads', 'ctwa_clid')) {
                $table->string('ctwa_clid')->nullable()->after('whasapp_user_id');
                $table->index('ctwa_clid', 'leads_ctwa_clid_idx');
            }

            if (! Schema::hasColumn('leads', 'whatsapp_business_account_id')) {
                $table->string('whatsapp_business_account_id', 64)->nullable()->after('ctwa_clid');
                $table->index('whatsapp_business_account_id', 'leads_waba_id_idx');
            }

            if (! Schema::hasColumn('leads', 'number_whatsApp_companies')) {
                $table->string('number_whatsApp_companies')->nullable()->after('whatsapp_business_account_id');
            }

            if (! Schema::hasColumn('leads', 'WhatsApp_username')) {
                $table->string('WhatsApp_username')->nullable()->after('number_whatsApp_companies');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'whasapp_user_id')) {
                $table->dropIndex('leads_whasapp_user_id_idx');
            }

            if (Schema::hasColumn('leads', 'ctwa_clid')) {
                $table->dropIndex('leads_ctwa_clid_idx');
            }

            if (Schema::hasColumn('leads', 'whatsapp_business_account_id')) {
                $table->dropIndex('leads_waba_id_idx');
            }

            foreach ([
                'WhatsApp_username',
                'number_whatsApp_companies',
                'whatsapp_business_account_id',
                'ctwa_clid',
                'whasapp_user_id',
            ] as $column) {
                if (Schema::hasColumn('leads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
