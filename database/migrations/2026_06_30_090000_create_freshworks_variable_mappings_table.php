<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('freshworks_variable_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->string('target_variable');
            $table->string('lead_field');
            $table->string('expected_value');
            $table->text('mapped_value')->nullable();
            $table->unsignedInteger('order')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['integration_id', 'target_variable', 'lead_field'], 'freshworks_mapping_variable_field_index');
            $table->index(['integration_id', 'active', 'order'], 'freshworks_mapping_active_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('freshworks_variable_mappings');
    }
};
