<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monday_board_column_mappings', function (Blueprint $table) {
            $table->string('source_type', 30)->nullable()->after('lead_field_name');
            $table->text('static_value')->nullable()->after('source_type');
        });

        DB::table('monday_board_column_mappings')
            ->whereNull('source_type')
            ->update(['source_type' => 'lead_field']);
    }

    public function down(): void
    {
        Schema::table('monday_board_column_mappings', function (Blueprint $table) {
            $table->dropColumn(['source_type', 'static_value']);
        });
    }
};
