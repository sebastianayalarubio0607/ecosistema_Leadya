<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('integrations', 'hubspot_return_mode')) {
            Schema::table('integrations', function (Blueprint $table): void {
                $table->string('hubspot_return_mode', 16)->default('webhook')->after('dealstage');
                $table->string('hubspot_pipeline_id', 255)->nullable()->after('hubspot_return_mode');
            });
        } elseif (! Schema::hasColumn('integrations', 'hubspot_pipeline_id')) {
            Schema::table('integrations', function (Blueprint $table): void {
                $table->string('hubspot_pipeline_id', 255)->nullable()->after('hubspot_return_mode');
            });
        }

        if (! Schema::hasTable('hubspot_opportunity_sync_runs')) {
            Schema::create('hubspot_opportunity_sync_runs', function (Blueprint $table): void {
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
                $table->index(['integration_id', 'created_at'], 'hubspot_runs_integration_created_index');
                $table->index(['status', 'created_at'], 'hubspot_runs_status_created_index');
            });
        }

        if (! Schema::hasTable('hubspot_opportunity_sync_logs')) {
            Schema::create('hubspot_opportunity_sync_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('sync_run_id')->constrained('hubspot_opportunity_sync_runs')->cascadeOnDelete();
                $table->foreignId('integration_id')->nullable()->constrained('integrations')->nullOnDelete();
                $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
                $table->string('opportunity_id', 255);
                $table->string('previous_crm_state', 255)->nullable();
                $table->string('new_crm_state', 255)->nullable();
                $table->string('pipeline_id', 255)->nullable();
                $table->string('stage_id', 255)->nullable();
                $table->string('state_status', 20)->default('updated');
                $table->text('message')->nullable();
                $table->timestamps();
                $table->index(['sync_run_id', 'state_status'], 'hubspot_logs_run_state_index');
                $table->index(['lead_id', 'created_at'], 'hubspot_logs_lead_created_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hubspot_opportunity_sync_logs');
        Schema::dropIfExists('hubspot_opportunity_sync_runs');

        if (Schema::hasColumn('integrations', 'hubspot_return_mode')) {
            Schema::table('integrations', function (Blueprint $table): void {
                $table->dropColumn(['hubspot_pipeline_id', 'hubspot_return_mode']);
            });
        }
    }
};
