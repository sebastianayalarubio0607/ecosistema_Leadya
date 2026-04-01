<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monday_board_column_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monday_board_id')->constrained('monday_boards')->cascadeOnDelete();
            $table->foreignId('monday_board_column_id')->constrained('monday_board_columns')->cascadeOnDelete();
            $table->string('lead_field_name')->nullable();
            $table->timestamps();

            $table->unique(['monday_board_id', 'monday_board_column_id'], 'monday_board_column_mappings_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monday_board_column_mappings');
    }
};
