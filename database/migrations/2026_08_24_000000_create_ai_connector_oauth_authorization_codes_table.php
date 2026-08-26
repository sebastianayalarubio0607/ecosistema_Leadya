<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_connector_oauth_authorization_codes')) {
            return;
        }

        Schema::create('ai_connector_oauth_authorization_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_connector_id')->constrained('ai_connectors')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('code_hash', 64)->unique();
            $table->text('redirect_uri');
            $table->json('scopes')->nullable();
            $table->string('code_challenge', 191)->nullable();
            $table->string('code_challenge_method', 20)->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable()->index();
            $table->timestamps();

            $table->index(['ai_connector_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_connector_oauth_authorization_codes');
    }
};
