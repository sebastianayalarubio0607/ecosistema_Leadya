<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('meta_ad_sets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('meta_campaign_id')
                ->constrained('meta_campaigns')
                ->cascadeOnDelete();

            $table->string('meta_ad_set_id', 64)->unique(); // adset_id
            $table->string('name')->nullable();             // adset_name

            $table->string('optimization_goal', 100)->nullable();
            $table->string('attribution_setting', 100)->nullable();

            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            $table->index(['meta_campaign_id', 'meta_ad_set_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ad_sets');
    }
};
