<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_access_tokens', function (Blueprint $table) {
            if (! Schema::hasColumn('meta_access_tokens', 'meta_app_id')) {
                $table->string('meta_app_id')->nullable()->after('long_lived_token');
            }

            if (! Schema::hasColumn('meta_access_tokens', 'meta_app_secret')) {
                $table->string('meta_app_secret')->nullable()->after('meta_app_id');
            }
        });

        Schema::table('meta_form_field_mappings', function (Blueprint $table) {
            if (! Schema::hasColumn('meta_form_field_mappings', 'static_value')) {
                $table->string('static_value')->nullable()->after('lead_field_name');
            }
        });

        if (Schema::hasTable('meta_form_field_mappings') && Schema::hasColumn('meta_form_field_mappings', 'meta_field_name')) {
            $driver = DB::getDriverName();

            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE meta_form_field_mappings MODIFY meta_field_name VARCHAR(255) NULL');
            } elseif ($driver === 'pgsql') {
                DB::statement('ALTER TABLE meta_form_field_mappings ALTER COLUMN meta_field_name DROP NOT NULL');
            }
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (Schema::hasTable('meta_form_field_mappings') && Schema::hasColumn('meta_form_field_mappings', 'meta_field_name')) {
            if ($driver === 'mysql') {
                DB::statement("UPDATE meta_form_field_mappings SET meta_field_name = CONCAT('__static__', id) WHERE meta_field_name IS NULL");
                DB::statement('ALTER TABLE meta_form_field_mappings MODIFY meta_field_name VARCHAR(255) NOT NULL');
            } elseif ($driver === 'pgsql') {
                DB::statement("UPDATE meta_form_field_mappings SET meta_field_name = '__static__' || id WHERE meta_field_name IS NULL");
                DB::statement('ALTER TABLE meta_form_field_mappings ALTER COLUMN meta_field_name SET NOT NULL');
            }
        }

        Schema::table('meta_form_field_mappings', function (Blueprint $table) {
            if (Schema::hasColumn('meta_form_field_mappings', 'static_value')) {
                $table->dropColumn('static_value');
            }
        });

        Schema::table('meta_access_tokens', function (Blueprint $table) {
            if (Schema::hasColumn('meta_access_tokens', 'meta_app_secret')) {
                $table->dropColumn('meta_app_secret');
            }

            if (Schema::hasColumn('meta_access_tokens', 'meta_app_id')) {
                $table->dropColumn('meta_app_id');
            }
        });
    }
};
