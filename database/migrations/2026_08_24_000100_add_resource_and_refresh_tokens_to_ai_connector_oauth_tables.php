<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_connector_access_tokens')) {
            Schema::table('ai_connector_access_tokens', function (Blueprint $table) {
                if (! Schema::hasColumn('ai_connector_access_tokens', 'resource')) {
                    $table->text('resource')->nullable()->after('scopes');
                }

                if (! Schema::hasColumn('ai_connector_access_tokens', 'refresh_token_encrypted')) {
                    $table->text('refresh_token_encrypted')->nullable()->after('access_token_hash');
                }

                if (! Schema::hasColumn('ai_connector_access_tokens', 'refresh_token_hash')) {
                    $table->char('refresh_token_hash', 64)->nullable()->unique()->after('refresh_token_encrypted');
                }

                if (! Schema::hasColumn('ai_connector_access_tokens', 'refresh_token_expires_at')) {
                    $table->timestamp('refresh_token_expires_at')->nullable()->index()->after('expires_at');
                }

                if (! Schema::hasColumn('ai_connector_access_tokens', 'refresh_token_revoked_at')) {
                    $table->timestamp('refresh_token_revoked_at')->nullable()->index()->after('refresh_token_expires_at');
                }
            });
        }

        if (Schema::hasTable('ai_connector_oauth_authorization_codes')) {
            Schema::table('ai_connector_oauth_authorization_codes', function (Blueprint $table) {
                if (! Schema::hasColumn('ai_connector_oauth_authorization_codes', 'resource')) {
                    $table->text('resource')->nullable()->after('redirect_uri');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('ai_connector_oauth_authorization_codes', function (Blueprint $table) {
            $table->dropColumn('resource');
        });

        Schema::table('ai_connector_access_tokens', function (Blueprint $table) {
            $table->dropUnique(['refresh_token_hash']);
            $table->dropColumn([
                'resource',
                'refresh_token_encrypted',
                'refresh_token_hash',
                'refresh_token_expires_at',
                'refresh_token_revoked_at',
            ]);
        });
    }
};
