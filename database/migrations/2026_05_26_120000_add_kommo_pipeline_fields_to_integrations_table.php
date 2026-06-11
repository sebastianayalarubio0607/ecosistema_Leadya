<?php

use App\Models\Integrationtype;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->string('kommo_pipeline_default_pipeline_id')->nullable()->after('dealstage');
            $table->string('kommo_pipeline_default_pipeline_name')->nullable()->after('kommo_pipeline_default_pipeline_id');
            $table->string('kommo_pipeline_default_status_id')->nullable()->after('kommo_pipeline_default_pipeline_name');
            $table->string('kommo_pipeline_default_status_name')->nullable()->after('kommo_pipeline_default_status_id');
        });

        Integrationtype::firstOrCreate(
            ['name' => 'KommoPipeline'],
            [
                'description' => 'Kommo con pipeline y status dinamicos por condiciones',
                'status' => 1,
            ]
        );
    }

    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn([
                'kommo_pipeline_default_pipeline_id',
                'kommo_pipeline_default_pipeline_name',
                'kommo_pipeline_default_status_id',
                'kommo_pipeline_default_status_name',
            ]);
        });
    }
};
