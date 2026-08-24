<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_connector_query_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_connector_id')->constrained('ai_connectors')->cascadeOnDelete();
            $table->string('tool_name', 80);
            $table->char('query_hash', 64);
            $table->string('status', 30)->default('ok')->index();
            $table->boolean('cache_hit')->default(false)->index();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['ai_connector_id', 'created_at']);
            $table->index(['ai_connector_id', 'tool_name', 'created_at'], 'ai_connector_tool_created_idx');
            $table->index(['ai_connector_id', 'query_hash', 'created_at'], 'ai_connector_query_hash_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_connector_query_logs');
    }
};
