<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lead_crm_state_conversion_sequences')) {
            return;
        }

        Schema::create('lead_crm_state_conversion_sequences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('lead_id')->index();
            $table->unsignedInteger('current_position')->default(0);
            $table->unsignedInteger('total_jobs');
            $table->string('status', 24)->default('active')->index();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_crm_state_conversion_sequences');
    }
};
