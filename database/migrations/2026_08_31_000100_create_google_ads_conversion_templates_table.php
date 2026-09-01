<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('google_ads_conversion_templates')) {
            Schema::create('google_ads_conversion_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('category', 80)->default('SUBMIT_LEAD_FORM')->index();
                $table->string('type', 80)->default('UPLOAD_CLICKS')->index();
                $table->string('google_status', 32)->default('ENABLED')->index();
                $table->boolean('primary_for_goal')->default(false)->index();
                $table->unsignedSmallInteger('click_through_lookback_window_days')->default(30);
                $table->decimal('default_value', 16, 2)->default(0);
                $table->string('default_currency_code', 3)->default('COP');
                $table->boolean('always_use_default_value')->default(false);
                $table->boolean('estado_lq')->default(true)->index();
                $table->timestamps();

                $table->index('created_at');
            });
        }

        $now = now();

        foreach ($this->defaultTemplates($now) as $template) {
            DB::table('google_ads_conversion_templates')->updateOrInsert(
                ['name' => $template['name']],
                $template
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('google_ads_conversion_templates');
    }

    private function defaultTemplates($now): array
    {
        return [
            [
                'name' => 'API - REGISTRO FORM',
                'category' => 'SUBMIT_LEAD_FORM',
                'type' => 'UPLOAD_CLICKS',
                'google_status' => 'ENABLED',
                'primary_for_goal' => false,
                'click_through_lookback_window_days' => 30,
                'default_value' => 0.5,
                'default_currency_code' => 'COP',
                'always_use_default_value' => false,
                'estado_lq' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'API - CLIENTE POTENCIAL CUALIFICADO',
                'category' => 'QUALIFIED_LEAD',
                'type' => 'UPLOAD_CLICKS',
                'google_status' => 'ENABLED',
                'primary_for_goal' => false,
                'click_through_lookback_window_days' => 30,
                'default_value' => 1,
                'default_currency_code' => 'COP',
                'always_use_default_value' => false,
                'estado_lq' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'API - OPORTUNIDAD',
                'category' => 'CONVERTED_LEAD',
                'type' => 'UPLOAD_CLICKS',
                'google_status' => 'ENABLED',
                'primary_for_goal' => true,
                'click_through_lookback_window_days' => 30,
                'default_value' => 3,
                'default_currency_code' => 'COP',
                'always_use_default_value' => false,
                'estado_lq' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'API - VENTA',
                'category' => 'PURCHASE',
                'type' => 'UPLOAD_CLICKS',
                'google_status' => 'ENABLED',
                'primary_for_goal' => true,
                'click_through_lookback_window_days' => 30,
                'default_value' => 10,
                'default_currency_code' => 'COP',
                'always_use_default_value' => false,
                'estado_lq' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];
    }
};
