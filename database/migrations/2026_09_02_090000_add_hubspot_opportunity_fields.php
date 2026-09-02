<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('crm_id_oportunidad', 255)->nullable()->after('crm_id')->index();
        });

        Schema::table('integrations', function (Blueprint $table) {
            $table->longText('body_oportunidad')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn('body_oportunidad');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('crm_id_oportunidad');
        });
    }
};
