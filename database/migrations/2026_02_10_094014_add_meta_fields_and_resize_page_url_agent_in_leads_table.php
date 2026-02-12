<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // nuevos campos
            if (!Schema::hasColumn('leads', 'meta_id_ad')) {
                $table->string('meta_id_ad', 255)->nullable()->after('campaign_origin');
                $table->index('meta_id_ad');
            }

            if (!Schema::hasColumn('leads', 'value')) {
                $table->decimal('value', 12, 2)->nullable()->after('meta_id_ad');
            }

            // aumentar tamaños a 1000
            // Nota: change() puede requerir doctrine/dbal dependiendo del driver.
            if (Schema::hasColumn('leads', 'page_url')) {
                $table->string('page_url', 1000)->nullable()->change();
            }

            if (Schema::hasColumn('leads', 'agent')) {
                $table->string('agent', 1000)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'meta_id_ad')) {
                $table->dropIndex(['meta_id_ad']);
                $table->dropColumn('meta_id_ad');
            }

            if (Schema::hasColumn('leads', 'value')) {
                $table->dropColumn('value');
            }

            // opcional: si antes eran 255, lo devuelves a 255
            if (Schema::hasColumn('leads', 'page_url')) {
                $table->string('page_url', 255)->nullable()->change();
            }

            if (Schema::hasColumn('leads', 'agent')) {
                $table->string('agent', 255)->nullable()->change();
            }
        });
    }
};
