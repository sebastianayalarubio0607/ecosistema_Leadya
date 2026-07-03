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
            ['name' => 'Lety'],
            [
                'description' => 'Webhooks condicionales form-urlencoded por campos del lead',
                'status' => 1,
            ]
        );

        Schema::create('lety_webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->mediumText('body');
            $table->unsignedInteger('order')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['integration_id', 'active', 'order']);
        });

        Schema::create('lety_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->foreignId('lety_webhook_id')->constrained('lety_webhooks')->cascadeOnDelete();
            $table->string('lead_field');
            $table->string('expected_value');
            $table->unsignedInteger('order')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['integration_id', 'active', 'order']);
            $table->index(['lety_webhook_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lety_conditions');
        Schema::dropIfExists('lety_webhooks');
    }
};
