<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('leads', 'value')) {
            return;
        }

        Schema::table('leads', function (Blueprint $table) {
            $table->decimal('value', 18, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('leads', 'value')) {
            return;
        }

        // Keep the wider column if existing values cannot fit in DECIMAL(12,2).
        $hasOutOfRangeValues = DB::table('leads')
            ->where(function ($query) {
                $query->where('value', '>', 9999999999.99)
                    ->orWhere('value', '<', -9999999999.99);
            })
            ->exists();

        if ($hasOutOfRangeValues) {
            return;
        }

        Schema::table('leads', function (Blueprint $table) {
            $table->decimal('value', 12, 2)->nullable()->change();
        });
    }
};
