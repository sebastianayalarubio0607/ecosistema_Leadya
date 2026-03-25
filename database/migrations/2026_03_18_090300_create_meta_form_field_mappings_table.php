<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_form_field_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_form_id')->constrained('meta_forms')->cascadeOnDelete();
            $table->string('meta_field_name');
            $table->string('lead_field_name');
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['meta_form_id', 'meta_field_name'], 'meta_form_field_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_form_field_mappings');
    }
};
