<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('meta_ad_accounts', function (Blueprint $table) {
            $table->id();

            $table->string('meta_account_id', 64)->unique(); // account_id de Meta
            $table->string('name')->nullable();              // account_name

            $table->string('status', 20)->default('active')->index(); // active|inactive
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ad_accounts');
    }
};
