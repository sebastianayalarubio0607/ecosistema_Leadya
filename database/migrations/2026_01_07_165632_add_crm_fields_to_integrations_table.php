<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->string('crm_Id_phone')->nullable();
            $table->string('crm_Id_service')->nullable();
            $table->string('crm_Id_fuente')->nullable();
            $table->string('crm_Id_email')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
               $table->dropColumn([
                'crm_Id_phone',
                'crm_Id_service',
                'crm_Id_fuente',
                'crm_Id_email',
            ]);
        });
    }
};
