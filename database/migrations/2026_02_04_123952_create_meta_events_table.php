<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('meta_events', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('estados')->default('activo');
            $table->timestamps();

            $table->index('nombre');
            $table->index('estados');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_events');
    }
};