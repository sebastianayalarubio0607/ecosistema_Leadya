<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('origins', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('origins')->insert([
            ['code' => 'gads', 'name' => 'Google Ads', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'yt', 'name' => 'YouTube Ads', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'gm', 'name' => 'Google Maps', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'gs', 'name' => 'Google Shopping', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'fb', 'name' => 'Facebook', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'ig', 'name' => 'Instagram', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'fbm', 'name' => 'Messenger', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'wa', 'name' => 'WhatsApp', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('origins');
    }
};
