<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('meta_page_id')->unique();
            $table->string('name');
            $table->longText('page_access_token')->nullable();
            $table->boolean('status')->default(false)->index();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_token_refresh_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_pages');
    }
};
