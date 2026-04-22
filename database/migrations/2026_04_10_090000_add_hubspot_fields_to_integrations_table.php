<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->string('url_consulta_lead')->nullable()->after('body');
            $table->string('url_negocio')->nullable()->after('url_consulta_lead');
            $table->string('url_creacionlead')->nullable()->after('url_negocio');
            $table->text('dealname')->nullable()->after('url_creacionlead');
            $table->string('dealstage')->nullable()->after('dealname');
        });
    }

    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn([
                'url_consulta_lead',
                'url_negocio',
                'url_creacionlead',
                'dealname',
                'dealstage',
            ]);
        });
    }
};
