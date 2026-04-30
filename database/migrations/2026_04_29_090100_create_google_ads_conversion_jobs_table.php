<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_ads_conversion_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('crm_state_id', 255)->nullable()->index();
            $table->string('status')->nullable()->index();
            $table->string('conversion_action_id', 64)->nullable();
            $table->string('conversion_action_resource_name')->nullable();
            $table->string('order_id')->nullable()->index();
            $table->string('click_identifier_type', 20)->nullable();
            $table->string('click_identifier_value')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->longText('payload')->nullable();
            $table->longText('response')->nullable();
            $table->boolean('success')->default(false)->index();
            $table->boolean('partial_failure')->default(false)->index();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['lead_id', 'order_id'], 'gads_conversion_lead_order_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_ads_conversion_jobs');
    }
};
