<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lead_funnel_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('funnel_id');
            $table->timestamps();

            $table->index(['lead_id', 'created_at']);
            $table->index(['funnel_id', 'created_at']);

            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
            $table->foreign('funnel_id')->references('id')->on('funnels')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_funnel_histories');
    }
};