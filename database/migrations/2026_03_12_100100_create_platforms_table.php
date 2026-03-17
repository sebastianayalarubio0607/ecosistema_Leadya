<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platforms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('platforms')->insert([
            ['code' => 'search', 'name' => 'Search', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'display', 'name' => 'Display', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'video', 'name' => 'Video', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'apps', 'name' => 'Apps', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'discovery', 'name' => 'Discovery', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'max', 'name' => 'Performance Max', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'demand', 'name' => 'Demand Gen', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'reels', 'name' => 'Reels', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'shorts', 'name' => 'Shorts', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'img', 'name' => 'Imagen', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'slider', 'name' => 'carrusel', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('platforms');
    }
};
