<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geos', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('geos')->insert([
            ['code' => 'bog', 'name' => 'Bogota', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'clo', 'name' => 'Cali', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'baq', 'name' => 'Barranquilla', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'med', 'name' => 'Medellin', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'mex', 'name' => 'Mexico DF', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'us', 'name' => 'USA', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'latam', 'name' => 'Latam', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'eu', 'name' => 'Europa', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('geos');
    }
};
