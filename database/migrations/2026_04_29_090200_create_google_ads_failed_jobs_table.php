<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_ads_failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('crm_state_id', 255)->nullable()->index();
            $table->string('status')->nullable()->index();
            $table->string('job_class')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->longText('payload')->nullable();
            $table->longText('response')->nullable();
            $table->text('error_message')->nullable();
            $table->longText('exception')->nullable();
            $table->timestamp('failed_at')->nullable()->index();
            $table->timestamp('retried_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_ads_failed_jobs');
    }
};
