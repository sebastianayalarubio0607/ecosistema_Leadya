<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_ads_credentials', function (Blueprint $table) {
            $table->id();
            $table->longText('mcc_developer_token');
            $table->longText('client_id');
            $table->longText('client_secret');
            $table->longText('refresh_token');
            $table->longText('access_token')->nullable();
            $table->longText('customer_id')->nullable();
            $table->longText('mcc_id')->nullable();
            $table->timestamp('access_token_expires_at')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_ads_credentials');
    }
};
