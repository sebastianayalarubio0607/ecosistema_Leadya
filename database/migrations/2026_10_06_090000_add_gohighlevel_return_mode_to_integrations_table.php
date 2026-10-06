<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('integrations', 'gohighlevel_return_mode')) {
            Schema::table('integrations', function (Blueprint $table) {
                $table->string('gohighlevel_return_mode', 16)->default('webhook')->after('location_id');
            });
        }

        if (! Schema::hasTable('gohighlevel_opportunity_sync_runs')) {
            Schema::create('gohighlevel_opportunity_sync_runs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('integration_id')->nullable()->constrained('integrations')->nullOnDelete();
                $table->string('trigger_source', 20)->default('scheduled');
                $table->string('status', 20)->default('pending');
                $table->unsignedInteger('opportunities_checked')->default(0);
                $table->unsignedInteger('leads_updated')->default(0);
                $table->unsignedInteger('leads_not_found')->default(0);
                $table->text('error_message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->index(['integration_id', 'created_at'], 'ghl_sync_runs_integration_created_index');
                $table->index(['status', 'created_at'], 'ghl_sync_runs_status_created_index');
            });
        }

        if (! Schema::hasTable('gohighlevel_opportunity_sync_logs')) {
            Schema::create('gohighlevel_opportunity_sync_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sync_run_id')->constrained('gohighlevel_opportunity_sync_runs')->cascadeOnDelete();
                $table->foreignId('integration_id')->nullable()->constrained('integrations')->nullOnDelete();
                $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
                $table->string('opportunity_id', 255);
                $table->string('crm_state_id', 255)->nullable();
                $table->string('previous_crm_state', 255)->nullable();
                $table->string('new_crm_state', 255)->nullable();
                $table->string('pipeline_id', 255)->nullable();
                $table->string('stage_id', 255)->nullable();
                $table->string('conversion_channel', 20)->default('none');
                $table->string('state_status', 20)->default('updated');
                $table->string('facebook_conversion_status', 20)->default('omitted');
                $table->string('google_ads_conversion_status', 20)->default('omitted');
                $table->text('message')->nullable();
                $table->timestamp('conversion_requested_at')->nullable();
                $table->timestamps();
                $table->index(['sync_run_id', 'state_status'], 'ghl_sync_logs_run_state_index');
                $table->index(['lead_id', 'created_at'], 'ghl_sync_logs_lead_created_index');
            });
        }

        if (! Schema::hasTable('gohighlevel_sync_jobs')) {
            Schema::create('gohighlevel_sync_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gohighlevel_sync_jobs');
        Schema::dropIfExists('gohighlevel_opportunity_sync_logs');
        Schema::dropIfExists('gohighlevel_opportunity_sync_runs');

        if (Schema::hasColumn('integrations', 'gohighlevel_return_mode')) {
            Schema::table('integrations', function (Blueprint $table) {
                $table->dropColumn('gohighlevel_return_mode');
            });
        }
    }
};
