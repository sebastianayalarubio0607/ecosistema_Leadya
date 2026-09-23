<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funnels', function (Blueprint $table) {
            // Nullable for funnels created by existing internal services.
            $table->unsignedInteger('orden')->nullable()->index();
        });

        DB::transaction(function (): void {
            $funnels = DB::table('funnels')->orderBy('name')->orderBy('id')->lockForUpdate()->get(['id']);

            foreach ($funnels as $index => $funnel) {
                DB::table('funnels')->where('id', $funnel->id)->update(['orden' => ($index + 1) * 5]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('funnels', function (Blueprint $table) {
            $table->dropIndex(['orden']);
            $table->dropColumn('orden');
        });
    }
};
