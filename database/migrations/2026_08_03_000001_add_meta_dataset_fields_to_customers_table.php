<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'Meta_dataset')) {
                $table->boolean('Meta_dataset')->default(false)->after('fb_test_event_code');
            }

            if (! Schema::hasColumn('customers', 'Meta_dataset_id')) {
                $table->string('Meta_dataset_id')->nullable()->after('Meta_dataset');
            }

            if (! Schema::hasColumn('customers', 'Meta_dataset_token')) {
                $table->string('Meta_dataset_token', 500)->nullable()->after('Meta_dataset_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('customers', 'Meta_dataset_token') ? 'Meta_dataset_token' : null,
                Schema::hasColumn('customers', 'Meta_dataset_id') ? 'Meta_dataset_id' : null,
                Schema::hasColumn('customers', 'Meta_dataset') ? 'Meta_dataset' : null,
            ]));

            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
