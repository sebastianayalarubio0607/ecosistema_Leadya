<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_state_google_ads_conversions', function (Blueprint $table) {
            $table->id();
            $table->string('crm_state_id', 255)->index();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('conversion_action_id', 64);
            $table->string('conversion_action_name')->nullable();
            $table->string('conversion_action_resource_name');
            $table->timestamps();

            $table->foreign('crm_state_id')
                ->references('id')
                ->on('crm_state')
                ->cascadeOnDelete();

            $table->unique(['crm_state_id', 'customer_id'], 'crm_state_gads_customer_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_state_google_ads_conversions');
    }
};
