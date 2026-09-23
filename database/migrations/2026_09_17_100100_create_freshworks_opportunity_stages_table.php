<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('freshworks_opportunity_stages')) {
            return;
        }

        Schema::create('freshworks_opportunity_stages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('integration_id')->index();
            $table->string('pipeline_id', 255);
            $table->string('stage_id', 255);
            $table->string('crm_state_id', 255);
            $table->string('pipeline_name', 255);
            $table->string('stage_name', 255);
            $table->timestamps();
            $table->unique(['integration_id', 'pipeline_id', 'stage_id'], 'freshworks_opportunity_stage_unique');
            $table->index(['integration_id', 'stage_id'], 'freshworks_opportunity_stage_lookup');
        });
    }

    public function down(): void
    {
        // New configuration data is deliberately retained to avoid destructive rollbacks.
    }
};
