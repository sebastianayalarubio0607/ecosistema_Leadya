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
        Schema::create('platform_source', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained('platforms')->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['platform_id', 'source_id']);
        });

        $googleSourceId = $this->sourceId('google', 'Google Ads');
        $metaSourceId = $this->sourceId('meta', 'Meta Ads');

        $mapping = [
            'Apps' => [$metaSourceId],
            'carrusel' => [$metaSourceId],
            'Demand Gen' => [$googleSourceId],
            'Discovery' => [$googleSourceId],
            'Display' => [$googleSourceId],
            'Imagen' => [$googleSourceId, $metaSourceId],
            'Performance Max' => [$googleSourceId],
            'pruebas' => [$metaSourceId],
            'Reels' => [$metaSourceId],
            'Search' => [$googleSourceId],
            'Shorts' => [$googleSourceId],
            'Video' => [$googleSourceId, $metaSourceId],
        ];

        foreach ($mapping as $platformName => $sourceIds) {
            $platformId = $this->platformId($platformName);

            foreach (array_filter($sourceIds) as $sourceId) {
                DB::table('platform_source')->updateOrInsert(
                    ['platform_id' => $platformId, 'source_id' => $sourceId],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_source');
    }

    private function sourceId(string $code, string $name): int
    {
        $source = DB::table('sources')
            ->where('code', $code)
            ->orWhere('name', $name)
            ->orWhere('name', 'like', '%'.str_replace(' Ads', '', $name).'%')
            ->orderBy('id')
            ->first();

        if (! $source) {
            DB::table('sources')->insert([
                'code' => $code,
                'name' => $name,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return (int) DB::getPdo()->lastInsertId();
        }

        DB::table('sources')
            ->where('id', $source->id)
            ->update([
                'code' => $code,
                'is_active' => true,
                'updated_at' => now(),
            ]);

        return (int) $source->id;
    }

    private function platformId(string $name): int
    {
        $platform = DB::table('platforms')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->orderBy('id')
            ->first();

        if ($platform) {
            DB::table('platforms')
                ->where('id', $platform->id)
                ->update([
                    'is_active' => true,
                    'updated_at' => now(),
                ]);

            return (int) $platform->id;
        }

        DB::table('platforms')->insert([
            'code' => Str::slug($name, '_'),
            'name' => $name,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::getPdo()->lastInsertId();
    }
};
