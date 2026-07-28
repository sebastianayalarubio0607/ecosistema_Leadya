<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->char('event_hash', 64)->unique();
            $table->string('product')->nullable()->index();
            $table->string('object')->nullable()->index();
            $table->string('field')->nullable()->index();
            $table->string('app_id')->nullable()->index();
            $table->string('entry_id')->nullable()->index();
            $table->string('page_id')->nullable()->index();
            $table->string('account_id')->nullable()->index();
            $table->string('leadgen_id')->nullable()->index();
            $table->string('form_id')->nullable()->index();
            $table->string('ad_id')->nullable()->index();
            $table->string('adset_id')->nullable()->index();
            $table->string('campaign_id')->nullable()->index();
            $table->string('sender_id')->nullable()->index();
            $table->string('recipient_id')->nullable()->index();
            $table->timestamp('meta_event_time')->nullable();
            $table->timestamp('received_at')->index();
            $table->string('processing_status')->default('received')->index();
            $table->longText('processing_error')->nullable();
            $table->json('value')->nullable();
            $table->json('payload');
            $table->json('request_headers')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_webhook_events');
    }
};
