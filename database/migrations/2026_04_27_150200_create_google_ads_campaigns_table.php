<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_ads_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('google_ads_customer_id', 32);
            $table->string('google_campaign_id', 32);
            $table->string('campaign_name')->nullable();
            $table->string('campaign_status', 50)->nullable();
            $table->string('advertising_channel_type', 50)->nullable();
            $table->date('report_date')->index();
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->decimal('conversions', 16, 2)->default(0);
            $table->unsignedBigInteger('cost_micros')->default(0);
            $table->decimal('cost', 16, 6)->default(0);
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'google_campaign_id', 'report_date'], 'gads_campaign_unique_daily');
            $table->index(['customer_id', 'report_date'], 'gads_campaign_customer_report_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_ads_campaigns');
    }
};
