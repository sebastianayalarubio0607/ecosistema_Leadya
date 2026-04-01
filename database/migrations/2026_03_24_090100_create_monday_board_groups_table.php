<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monday_board_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monday_board_id')->constrained('monday_boards')->cascadeOnDelete();
            $table->string('monday_group_id', 100);
            $table->string('title');
            $table->timestamps();

            $table->unique(['monday_board_id', 'monday_group_id'], 'monday_board_groups_board_group_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monday_board_groups');
    }
};
