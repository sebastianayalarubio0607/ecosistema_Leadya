<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('integration_variable_conditions')) {
            return;
        }

        Schema::create('integration_variable_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->string('target_variable', 80);
            $table->string('source_type', 20)->default('lead');
            $table->string('source_key', 120);
            $table->string('operator', 50);
            $table->text('comparison_value')->nullable();
            $table->text('result_value')->nullable();
            $table->string('result_type', 30)->default('text');
            $table->unsignedInteger('order')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['integration_id', 'target_variable', 'active', 'order'], 'integration_var_conditions_target_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_variable_conditions');
    }
};
