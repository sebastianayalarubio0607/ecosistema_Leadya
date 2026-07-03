<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULT_CURRENCY_CODE = 'COP';
    private const DEFAULT_CURRENCY_NAME = 'Peso Colombiano';
    private const DEFAULT_LEAD_VALUE = 100000;

    public function up(): void
    {
        $copCurrencyId = $this->ensureCopCurrencyId();

        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'default_currency_id')) {
                $table->foreignId('default_currency_id')
                    ->nullable()
                    ->constrained('currencies')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('customers', 'default_lead_value')) {
                $table->decimal('default_lead_value', 16, 2)
                    ->default(self::DEFAULT_LEAD_VALUE)
                    ->after('default_currency_id');
            }
        });

        DB::table('customers')
            ->whereNull('default_currency_id')
            ->update(['default_currency_id' => $copCurrencyId]);

        DB::table('customers')
            ->where(function ($query) {
                $query->whereNull('default_lead_value')
                    ->orWhere('default_lead_value', '<=', 0);
            })
            ->update(['default_lead_value' => self::DEFAULT_LEAD_VALUE]);
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'default_currency_id')) {
                $table->dropConstrainedForeignId('default_currency_id');
            }

            if (Schema::hasColumn('customers', 'default_lead_value')) {
                $table->dropColumn('default_lead_value');
            }
        });
    }

    private function ensureCopCurrencyId(): int
    {
        $existingId = DB::table('currencies')
            ->where('code', self::DEFAULT_CURRENCY_CODE)
            ->value('id');

        if ($existingId) {
            return (int) $existingId;
        }

        return (int) DB::table('currencies')->insertGetId([
            'name' => self::DEFAULT_CURRENCY_NAME,
            'code' => self::DEFAULT_CURRENCY_CODE,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
