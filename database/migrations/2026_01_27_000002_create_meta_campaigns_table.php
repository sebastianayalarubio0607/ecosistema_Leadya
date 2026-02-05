<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('meta_campaigns', function (Blueprint $table) {
            $table->id();

            $table->foreignId('meta_ad_account_id')
                ->constrained('meta_ad_accounts')
                ->cascadeOnDelete();

            $table->string('meta_campaign_id', 64)->unique(); // campaign_id
            $table->string('name')->nullable();               // campaign_name

            $table->string('objective', 100)->nullable();
            $table->string('buying_type', 50)->nullable();

            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            $table->index(['meta_ad_account_id', 'meta_campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_campaigns');
    }
};
