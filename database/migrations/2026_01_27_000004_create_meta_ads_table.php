<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('meta_ads', function (Blueprint $table) {
            $table->id();

            $table->foreignId('meta_ad_set_id')
                ->constrained('meta_ad_sets')
                ->cascadeOnDelete();

            $table->string('meta_ad_id', 64)->unique(); // ad_id
            $table->string('name')->nullable();         // ad_name

            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            $table->index(['meta_ad_set_id', 'meta_ad_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads');
    }
};
