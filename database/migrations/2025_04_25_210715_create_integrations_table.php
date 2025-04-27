<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('description')->nullable();
            $table->foreignId('integrationtype_id')->nullable()->constrained('integrationtypes')->onDelete('cascade');
            $table->boolean('status')->nullable()->default(true);
            $table->foreignId('customer_id')->nullable() ->constrained('customers')->onDelete('cascade');
            $table->string('url')->nullable();
            $table->mediumText('tokent')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
