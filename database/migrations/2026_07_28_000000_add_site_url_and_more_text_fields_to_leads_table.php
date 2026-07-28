<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'site_url')) {
                $definition = $table->mediumText('site_url')->nullable();

                if (Schema::hasColumn('leads', 'page_url')) {
                    $definition->after('page_url');
                }
            }

            $afterColumn = Schema::hasColumn('leads', 'campo_text_5')
                ? 'campo_text_5'
                : (Schema::hasColumn('leads', 'campo_numero_5') ? 'campo_numero_5' : null);

            foreach (range(6, 15) as $index) {
                $column = "campo_text_{$index}";

                if (Schema::hasColumn('leads', $column)) {
                    $afterColumn = $column;
                    continue;
                }

                $definition = $table->text($column)->nullable();

                if ($afterColumn) {
                    $definition->after($afterColumn);
                }

                $afterColumn = $column;
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            foreach (array_reverse(array_map(fn ($index) => "campo_text_{$index}", range(6, 15))) as $column) {
                if (Schema::hasColumn('leads', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('leads', 'site_url')) {
                $table->dropColumn('site_url');
            }
        });
    }
};
