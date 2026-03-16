<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('languages')->insert([
            ['code' => 'es', 'name' => 'Español', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'en', 'name' => 'Inglés', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'pt', 'name' => 'Portugués', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
