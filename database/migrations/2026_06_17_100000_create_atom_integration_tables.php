<?php

use App\Models\Integrationtype;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Integrationtype::firstOrCreate(
            ['name' => 'Atom'],
            [
                'description' => 'Webhooks condicionales por campos del lead',
                'status' => 1,
            ]
        );

        Schema::create('atom_webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->unsignedInteger('order')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['integration_id', 'active', 'order']);
            $table->index(['integration_id', 'is_default']);
        });

        Schema::create('atom_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->foreignId('atom_webhook_id')->constrained('atom_webhooks')->cascadeOnDelete();
            $table->string('lead_field');
            $table->string('expected_value');
            $table->unsignedInteger('order')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['integration_id', 'active', 'order']);
            $table->index(['atom_webhook_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atom_conditions');
        Schema::dropIfExists('atom_webhooks');
    }
};
