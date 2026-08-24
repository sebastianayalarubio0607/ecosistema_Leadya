<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_connectors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('client_id', 80)->unique();
            $table->text('client_secret_encrypted');
            $table->char('client_secret_hash', 64)->unique();
            $table->string('client_secret_last_four', 8);
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('allow_ad_metrics')->default(false);
            $table->json('allowed_tools')->nullable();
            $table->json('allowed_origins')->nullable();
            $table->unsignedInteger('max_requests_per_minute')->default(20);
            $table->unsignedInteger('max_requests_per_day')->default(1000);
            $table->unsignedSmallInteger('min_request_interval_seconds')->default(1);
            $table->unsignedSmallInteger('max_date_range_days')->default(31);
            $table->unsignedInteger('cache_ttl_seconds')->default(300);
            $table->unsignedSmallInteger('access_token_ttl_minutes')->default(60);
            $table->text('notes')->nullable();
            $table->timestamp('last_used_at')->nullable()->index();
            $table->timestamp('last_rotated_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_connectors');
    }
};
