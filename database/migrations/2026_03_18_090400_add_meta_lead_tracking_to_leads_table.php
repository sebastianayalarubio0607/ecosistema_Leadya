<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'meta_lead_id')) {
                $table->string('meta_lead_id')->nullable()->unique()->after('meta_id_ad');
            }

            if (! Schema::hasColumn('leads', 'meta_page_id')) {
                $table->foreignId('meta_page_id')->nullable()->after('meta_lead_id')->constrained('meta_pages')->nullOnDelete();
            }

            if (! Schema::hasColumn('leads', 'meta_form_id')) {
                $table->foreignId('meta_form_id')->nullable()->after('meta_page_id')->constrained('meta_forms')->nullOnDelete();
            }

            if (! Schema::hasColumn('leads', 'meta_created_time')) {
                $table->timestamp('meta_created_time')->nullable()->after('meta_form_id');
            }

            if (! Schema::hasColumn('leads', 'meta_payload')) {
                $table->json('meta_payload')->nullable()->after('meta_created_time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'meta_payload')) {
                $table->dropColumn('meta_payload');
            }

            if (Schema::hasColumn('leads', 'meta_created_time')) {
                $table->dropColumn('meta_created_time');
            }

            if (Schema::hasColumn('leads', 'meta_form_id')) {
                $table->dropConstrainedForeignId('meta_form_id');
            }

            if (Schema::hasColumn('leads', 'meta_page_id')) {
                $table->dropConstrainedForeignId('meta_page_id');
            }

            if (Schema::hasColumn('leads', 'meta_lead_id')) {
                $table->dropUnique(['meta_lead_id']);
                $table->dropColumn('meta_lead_id');
            }
        });
    }
};
