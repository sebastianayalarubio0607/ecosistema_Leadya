<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('facebook_conversion_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('lead_id')->index();

            // Meta / evento
            $table->string('event_name')->default('Lead');
            $table->string('pixel_id')->nullable()->index();
            $table->string('action_source')->nullable()->default('website');
            $table->unsignedBigInteger('event_time')->nullable();
            $table->text('event_source_url')->nullable();

            // Contexto de identificación
            $table->string('fbp')->nullable();
            $table->string('fbc')->nullable();
            $table->string('client_ip')->nullable();
            $table->text('client_user_agent')->nullable();

            // Test Events (opcional)
            $table->string('test_event_code')->nullable();
            //$table->bigInteger('event_time');

            // Datos completos enviados/recibidos
            $table->json('user_data')->nullable();
            $table->json('custom_data')->nullable();
            $table->json('request_payload')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body')->nullable();

            // Resultado
            $table->boolean('success')->default(false);
            $table->unsignedInteger('attempt')->default(1);
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();

            // (Opcional) claves foráneas si las usas
            // $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            // $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();

            // Índices útiles para análisis
            $table->index(['customer_id', 'success', 'created_at']);
            $table->index(['pixel_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_conversion_logs');
    }
};
