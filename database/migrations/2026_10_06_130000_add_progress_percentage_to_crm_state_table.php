<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('crm_state', 'progress_percentage')) {
            return;
        }

        Schema::table('crm_state', function (Blueprint $table) {
            // No upper bound is imposed so custom CRM progress scales can exceed 100.
            $table->decimal('progress_percentage', 12, 2)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('crm_state', 'progress_percentage')) {
            Schema::table('crm_state', function (Blueprint $table) {
                $table->dropColumn('progress_percentage');
            });
        }
    }
};
