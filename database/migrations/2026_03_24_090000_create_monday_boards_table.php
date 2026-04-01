<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monday_boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->string('monday_board_id', 50);
            $table->string('name');
            $table->boolean('status')->default(false);
            $table->string('condition_lead_field')->nullable();
            $table->string('condition_expected_value')->nullable();
            $table->string('monday_group_id', 100)->nullable();
            $table->timestamp('boards_synced_at')->nullable();
            $table->timestamp('details_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['integration_id', 'monday_board_id'], 'monday_boards_integration_board_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monday_boards');
    }
};
