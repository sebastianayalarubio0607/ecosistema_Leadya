<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_links')) {
            Schema::create('site_links', function (Blueprint $table) {
                $table->id();
                $table->foreignId('source_id')
                    ->nullable()
                    ->constrained('sources')
                    ->nullOnDelete();
                $table->string('code', 20);
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['source_id', 'code']);
                $table->unique(['source_id', 'name']);
            });
        } else {
            Schema::table('site_links', function (Blueprint $table) {
                if (! Schema::hasColumn('site_links', 'source_id')) {
                    $table->foreignId('source_id')->nullable()->after('id');
                }

                if (! Schema::hasColumn('site_links', 'code')) {
                    $table->string('code', 20)->after('source_id');
                }

                if (! Schema::hasColumn('site_links', 'name')) {
                    $table->string('name')->after('code');
                }

                if (! Schema::hasColumn('site_links', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('name');
                }

                if (! Schema::hasColumn('site_links', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }

                if (! Schema::hasColumn('site_links', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });
        }

        $googleSourceId = DB::table('sources')
            ->where('name', 'like', '%Google%')
            ->orderBy('id')
            ->value('id');

        if ($googleSourceId) {
            foreach ([
                'COT' => 'Cotizacion',
                'AGE' => 'Agendamiento',
                'BEN' => 'Beneficios',
                'CAS' => 'Casos de exito',
                'TES' => 'Testimonios',
                'PRE' => 'Precios',
                'FAQ' => 'Preguntas frecuentes',
                'GAR' => 'Garantias',
                'SED' => 'Sedes o cobertura',
                'CON' => 'Contacto',
            ] as $code => $name) {
                DB::table('site_links')->updateOrInsert(
                    ['source_id' => $googleSourceId, 'code' => $code],
                    ['name' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_links');
    }
};
