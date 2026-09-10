<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('integration_variables')) {
            return;
        }

        Schema::create('integration_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->string('name', 80);
            $table->text('value');
            $table->string('type', 30)->default('text');
            $table->unsignedInteger('order')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['integration_id', 'name'], 'integration_variables_integration_name_unique');
            $table->index(['integration_id', 'active', 'order'], 'integration_variables_active_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_variables');
    }
};
