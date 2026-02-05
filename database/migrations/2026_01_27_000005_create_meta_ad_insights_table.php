<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('meta_ad_insights', function (Blueprint $table) {
            $table->id();

            $table->foreignId('meta_ad_id')
                ->constrained('meta_ads')
                ->cascadeOnDelete();

            // snapshot (por si quieres consultar sin joins)
            $table->string('account_id', 64)->index();
            $table->string('account_name')->nullable();

            $table->string('campaign_id', 64)->index();
            $table->string('campaign_name')->nullable();

            $table->string('adset_id', 64)->index();
            $table->string('adset_name')->nullable();

            $table->string('ad_id', 64)->index();
            $table->string('ad_name')->nullable();

            $table->string('objective', 100)->nullable();
            $table->string('optimization_goal', 100)->nullable();
            $table->string('buying_type', 50)->nullable();
            $table->string('attribution_setting', 120)->nullable();

            $table->unsignedBigInteger('impressions')->nullable();
            $table->unsignedBigInteger('reach')->nullable();
            $table->decimal('frequency', 10, 4)->nullable();
            $table->decimal('spend', 14, 2)->nullable();

            $table->unsignedBigInteger('clicks')->nullable();
            $table->unsignedBigInteger('unique_clicks')->nullable();
            $table->unsignedBigInteger('inline_link_clicks')->nullable();

            $table->decimal('cpm', 14, 4)->nullable();

            $table->json('actions')->nullable();

            $table->date('date_start')->index();
            $table->date('date_stop')->index();

            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            // 1 registro por anuncio por día (date_stop)
            $table->unique(['meta_ad_id', 'date_stop']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ad_insights');
    }
};
