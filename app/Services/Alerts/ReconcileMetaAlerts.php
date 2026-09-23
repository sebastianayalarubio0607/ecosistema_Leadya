<?php

namespace App\Services\Alerts;

use App\Models\MetaAdAccount;
use App\Models\MetaAdAccountStatusHistory;
use App\Support\MetaAdAccountId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReconcileMetaAlerts
{
    public function run(): bool
    {
        if (! app(AlertAvailability::class)->ready()) {
            return false;
        }
        $capture = app(MetaAlertObservationService::class);
        if (Schema::hasTable('meta_ad_account_status_histories')) {
            // Cursor and captured observations commit together. Replay is idempotent.
            DB::transaction(function () use ($capture) {
                $cursor = DB::table('alert_checkpoints')->where('name', 'meta_history')->lockForUpdate()->first();
                $rows = MetaAdAccountStatusHistory::with('account.customers')->where('id', '>', $cursor->last_id)->orderBy('id')->limit(500)->get();
                foreach ($rows as $row) {
                    $capture->history($row);
                }
                if ($rows->isNotEmpty()) {
                    DB::table('alert_checkpoints')->where('name', 'meta_history')->update(['last_id' => $rows->last()->id]);
                }
            });
            $lastId = DB::table('alert_checkpoints')->where('name', 'meta_history')->value('last_id');
            if (MetaAdAccountStatusHistory::where('id', '>', $lastId)->exists()) {
                return false;
            }
        }
        // Snapshot fallback also covers the import writer without querying Meta again.
        if (Schema::hasColumn('meta_ad_accounts', 'estado_meta')) {
            DB::transaction(function () use ($capture) {
                $cursor = DB::table('alert_checkpoints')->where('name', 'meta_snapshot')->lockForUpdate()->first();
                $accounts = MetaAdAccount::with('customers')->where('id', '>', $cursor->last_id)
                    ->whereNotNull('estado_meta')->whereNull('estado_meta_last_error')->orderBy('id')->limit(250)->get();
                $keys = $accounts->map(fn ($a) => 'meta:ad_account:'.MetaAdAccountId::normalize($a->meta_account_id));
                $states = DB::table('alert_source_states')->whereIn('source_key', $keys)->get()->keyBy('source_key');
                foreach ($accounts as $account) {
                    $key = 'meta:ad_account:'.MetaAdAccountId::normalize($account->meta_account_id);
                    $state = $states->get($key);
                    $at = $account->estado_meta_checked_at;
                    if (! $at || ($state && $state->observed_at && $at->lte($state->observed_at))) {
                        continue;
                    }
                    $capture->capture($account, 'snapshot:'.hash('sha256', $key.'|'.$at->format('Y-m-d H:i:s.u').'|'.$account->estado_meta),
                        $state?->state, $account->estado_meta, $at, $account->estado_meta_payload ?? []);
                }
                DB::table('alert_checkpoints')->where('name', 'meta_snapshot')->update(['last_id' => $accounts->last()?->id ?? 0]);
            });
        }

        return true;
    }
}
