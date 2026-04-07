<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->boolean('disable_integration_id_crm_prefix')
                ->nullable()
                ->after('crm_Id_email');

            $table->string('crm_id_prefix')
                ->nullable()
                ->after('disable_integration_id_crm_prefix');
        });
    }

    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn([
                'disable_integration_id_crm_prefix',
                'crm_id_prefix',
            ]);
        });
    }
};
