<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('crm_state', 'unmanaged')) {
            Schema::table('crm_state', function (Blueprint $table) {
                $table->boolean('unmanaged')
                    ->default(false)
                    ->after('meta_event_id')
                    ->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('crm_state', 'unmanaged')) {
            Schema::table('crm_state', function (Blueprint $table) {
                $table->dropIndex(['unmanaged']);
                $table->dropColumn('unmanaged');
            });
        }
    }
};
