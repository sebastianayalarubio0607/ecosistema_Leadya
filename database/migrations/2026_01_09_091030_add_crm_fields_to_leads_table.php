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
        Schema::table('leads', function (Blueprint $table) {
             $table->string('plataforma')->nullable();
            $table->string('lenguaje')->nullable();
            $table->string('geo')->nullable();

            // Normalmente el crm_id puede traer letras, guiones, etc.
            $table->string('crm_id', 255)->nullable();

            // FK a crm_state.id (string 255) -> debe ser string 255 también
            $table->string('crm_state', 255)->nullable()->index();

            $table->foreign('crm_state')
                ->references('id')
                ->on('crm_state')
                ->cascadeOnUpdate()
                ->nullOnDelete(); // si borran un crm_state, deja el campo null
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
           $table->dropForeign(['crm_state']);

            $table->dropColumn([
                'plataforma',
                'lenguaje',
                'geo',
                'crm_id',
                'crm_state',
            ]);
        });
    }
};
