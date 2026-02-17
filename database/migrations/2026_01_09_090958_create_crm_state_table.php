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
        Schema::create('crm_state', function (Blueprint $table) {
            $table->string('id', 255)->primary();

            $table->string('name');

            // FK hacia qualification.id (tu columna se llama "qualification")
            $table->unsignedBigInteger('qualification');

            $table->timestamps();

            $table->foreign('qualification')
                ->references('id')
                ->on('qualification')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->index('qualification');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_state');
    }
};
