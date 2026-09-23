<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('integrations') || Schema::hasColumn('integrations', 'omit_empty_payload_fields')) {
            return;
        }

        Schema::table('integrations', function (Blueprint $table) {
            // Existing integrations remain unchanged until their administrator
            // explicitly enables this GoHighLevel-Oportunidad option.
            $table->boolean('omit_empty_payload_fields')->default(false)->after('body_oportunidad');
        });
    }

    /**
     * Intentionally does not drop the column. This project preserves schema and
     * historical configuration during rollbacks as well.
     */
    public function down(): void
    {
        // Non-destructive by design.
    }
};
