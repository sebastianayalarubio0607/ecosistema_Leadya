<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->string('territory_id')->nullable()->after('code');
            $table->string('owner_id')->nullable()->after('territory_id');
            $table->string('city')->nullable()->after('owner_id');
            $table->string('lead_source_id')->nullable()->after('city');
            $table->text('custom_field')->nullable()->after('lead_source_id');
        });
    }

    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn([
                'territory_id',
                'owner_id',
                'city',
                'lead_source_id',
                'custom_field',
            ]);
        });
    }
};
