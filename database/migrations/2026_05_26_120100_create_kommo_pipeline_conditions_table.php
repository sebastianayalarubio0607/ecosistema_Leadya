<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kommo_pipeline_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->string('lead_field');
            $table->string('expected_value');
            $table->string('pipeline_id');
            $table->string('pipeline_name')->nullable();
            $table->string('status_id');
            $table->string('status_name')->nullable();
            $table->unsignedInteger('order')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['integration_id', 'active', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kommo_pipeline_conditions');
    }
};
