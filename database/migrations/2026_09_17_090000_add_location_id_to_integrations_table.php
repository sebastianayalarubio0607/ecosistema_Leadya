<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('integrations', 'location_id')) {
            Schema::table('integrations', function (Blueprint $table) {
                $table->string('location_id', 100)->nullable()->after('tokent');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('integrations', 'location_id')) {
            Schema::table('integrations', function (Blueprint $table) {
                $table->dropColumn('location_id');
            });
        }
    }
};
