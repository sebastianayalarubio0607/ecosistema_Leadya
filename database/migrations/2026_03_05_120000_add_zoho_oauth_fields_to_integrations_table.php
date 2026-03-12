<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->string('client_id')->nullable()->after('public_key');
            $table->text('client_secret')->nullable()->after('client_id');
            $table->text('refresh_token')->nullable()->after('client_secret');
            $table->string('api_domain')->nullable()->after('refresh_token');
            $table->text('scope')->nullable()->after('api_domain');
            $table->string('token_type', 30)->nullable()->after('scope');
            $table->unsignedInteger('expires_in')->nullable()->after('token_type');
            $table->timestamp('token_expires_at')->nullable()->after('expires_in');
        });
    }

    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn([
                'client_id',
                'client_secret',
                'refresh_token',
                'api_domain',
                'scope',
                'token_type',
                'expires_in',
                'token_expires_at',
            ]);
        });
    }
};
