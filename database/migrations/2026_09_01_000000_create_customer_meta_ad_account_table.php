<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customers') || ! Schema::hasTable('meta_ad_accounts')) {
            return;
        }

        if (! Schema::hasTable('customer_meta_ad_account')) {
            Schema::create('customer_meta_ad_account', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('meta_ad_account_id')->constrained('meta_ad_accounts')->cascadeOnDelete();
                $table->boolean('is_default_for_whatsapp_leads')->default(false)->index('cmaa_whatsapp_default_idx');
                $table->timestamps();

                $table->unique(['customer_id', 'meta_ad_account_id'], 'cmaa_customer_account_unique');
                $table->index(['meta_ad_account_id', 'created_at'], 'cmaa_account_created_idx');
            });
        }

        $this->backfillLegacyCustomerRelations();
        $this->ensureMissingWhatsappDefaults();
    }

    public function down(): void
    {
        if (! Schema::hasTable('customer_meta_ad_account')) {
            return;
        }

        if (DB::table('customer_meta_ad_account')->count() === 0) {
            Schema::dropIfExists('customer_meta_ad_account');
        }
    }

    private function backfillLegacyCustomerRelations(): void
    {
        if (! Schema::hasColumn('meta_ad_accounts', 'customer_id')) {
            return;
        }

        DB::table('meta_ad_accounts')
            ->join('customers', 'customers.id', '=', 'meta_ad_accounts.customer_id')
            ->whereNotNull('meta_ad_accounts.customer_id')
            ->orderBy('meta_ad_accounts.id')
            ->select([
                'meta_ad_accounts.id',
                'meta_ad_accounts.customer_id',
            ])
            ->chunk(500, function ($accounts): void {
                $now = now();

                foreach ($accounts as $account) {
                    DB::table('customer_meta_ad_account')->insertOrIgnore([
                        'customer_id' => $account->customer_id,
                        'meta_ad_account_id' => $account->id,
                        'is_default_for_whatsapp_leads' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    private function ensureMissingWhatsappDefaults(): void
    {
        $selectedByAccount = [];

        DB::table('customer_meta_ad_account as pivot')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('customer_meta_ad_account as defaults')
                    ->whereColumn('defaults.meta_ad_account_id', 'pivot.meta_ad_account_id')
                    ->where('defaults.is_default_for_whatsapp_leads', true);
            })
            ->orderBy('pivot.meta_ad_account_id')
            ->orderBy('pivot.created_at')
            ->orderBy('pivot.id')
            ->select([
                'pivot.id',
                'pivot.meta_ad_account_id',
            ])
            ->chunk(500, function ($rows) use (&$selectedByAccount): void {
                foreach ($rows as $row) {
                    $accountId = (int) $row->meta_ad_account_id;

                    if (isset($selectedByAccount[$accountId])) {
                        continue;
                    }

                    DB::table('customer_meta_ad_account')
                        ->where('id', $row->id)
                        ->update([
                            'is_default_for_whatsapp_leads' => true,
                            'updated_at' => now(),
                        ]);

                    $selectedByAccount[$accountId] = true;
                }
            });
    }
};
