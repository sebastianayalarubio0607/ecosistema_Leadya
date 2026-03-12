<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->integer('number_workers')->nullable();
            $table->integer('number_locations')->nullable();
            $table->integer('campo_numero_1')->nullable();
            $table->integer('campo_numero_2')->nullable();
            $table->integer('campo_numero_3')->nullable();
            $table->integer('campo_numero_4')->nullable();
            $table->integer('campo_numero_5')->nullable();

            $table->text('campo_text_1')->nullable();
            $table->text('campo_text_2')->nullable();
            $table->text('campo_text_3')->nullable();
            $table->text('campo_text_4')->nullable();
            $table->text('campo_text_5')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'number_workers',
                'number_locations',
                'campo_numero_1',
                'campo_numero_2',
                'campo_numero_3',
                'campo_numero_4',
                'campo_numero_5',
                'campo_text_1',
                'campo_text_2',
                'campo_text_3',
                'campo_text_4',
                'campo_text_5',
            ]);
        });
    }
};
