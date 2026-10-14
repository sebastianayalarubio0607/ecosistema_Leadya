<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pipelines')) {
            Schema::create('pipelines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
                $table->string('external_id', 255);
                $table->string('name', 255);
                $table->timestamps();
                $table->unique(['integration_id', 'external_id'], 'pipelines_integration_external_unique');
                $table->index('name');
            });
        }

        if (Schema::hasTable('crm_state') && ! Schema::hasColumn('crm_state', 'pipeline_id')) {
            Schema::table('crm_state', function (Blueprint $table): void {
                $table->foreignId('pipeline_id')
                    ->nullable()
                    ->after('qualification')
                    ->constrained('pipelines')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('crm_state', 'pipeline_id')) {
            Schema::table('crm_state', function (Blueprint $table): void {
                $table->dropForeign(['pipeline_id']);
                $table->dropColumn('pipeline_id');
            });
        }

        Schema::dropIfExists('pipelines');
    }
};
