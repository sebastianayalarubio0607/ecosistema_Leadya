<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->unique()->after('id');
        });

        DB::table('sources')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->each(function ($source) {
                $sourceName = mb_strtolower((string) $source->name);
                $baseCode = match (true) {
                    str_contains($sourceName, 'google') => 'google',
                    str_contains($sourceName, 'meta') => 'meta',
                    default => Str::slug((string) $source->name, '_') ?: 'source_'.$source->id,
                };
                $code = Str::limit($baseCode, 20, '');
                $candidate = $code;
                $suffix = 1;

                while (DB::table('sources')->where('code', $candidate)->where('id', '!=', $source->id)->exists()) {
                    $suffixText = '_'.$suffix++;
                    $candidate = Str::limit($code, 20 - strlen($suffixText), '').$suffixText;
                }

                DB::table('sources')
                    ->where('id', $source->id)
                    ->update(['code' => $candidate]);
            });
    }

    public function down(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
