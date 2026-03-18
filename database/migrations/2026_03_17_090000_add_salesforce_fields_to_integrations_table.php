<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->string('url_credenciales')->nullable()->after('custom_field');
            $table->string('username')->nullable()->after('url_credenciales');
            $table->text('password')->nullable()->after('username');
            $table->longText('body')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn([
                'url_credenciales',
                'username',
                'password',
                'body',
            ]);
        });
    }
};
