<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            if (! Schema::hasColumn('integrations', 'urldestino')) {
                $table->text('urldestino')->nullable()->after('url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            if (Schema::hasColumn('integrations', 'urldestino')) {
                $table->dropColumn('urldestino');
            }
        });
    }
};
