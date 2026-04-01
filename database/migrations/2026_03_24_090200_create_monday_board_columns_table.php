<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monday_board_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monday_board_id')->constrained('monday_boards')->cascadeOnDelete();
            $table->string('monday_column_id', 100);
            $table->string('title');
            $table->string('type', 100)->nullable();
            $table->longText('settings_json')->nullable();
            $table->timestamps();

            $table->unique(['monday_board_id', 'monday_column_id'], 'monday_board_columns_board_column_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monday_board_columns');
    }
};
