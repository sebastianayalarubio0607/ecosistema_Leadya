<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_connector_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_connector_id')->constrained('ai_connectors')->cascadeOnDelete();
            $table->text('access_token_encrypted');
            $table->char('access_token_hash', 64)->unique();
            $table->json('scopes')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();

            $table->index(['ai_connector_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_connector_access_tokens');
    }
};
