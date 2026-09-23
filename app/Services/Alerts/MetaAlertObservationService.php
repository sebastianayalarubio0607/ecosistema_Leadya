<?php

namespace App\Services\Alerts;

use App\Models\Alerts\AlertObservation;
use App\Models\MetaAdAccount;
use App\Models\MetaAdAccountStatusHistory;
use App\Support\MetaAdAccountId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MetaAlertObservationService
{
    public function history(MetaAdAccountStatusHistory $history): void
    {
        if ($history->error || ! $history->account) {
            return;
        }
        $this->capture($history->account, 'history:'.$history->id,
            $history->estado_meta_anterior, $history->estado_meta_nuevo,
            $history->consulted_at, $history->payload ?? []);
    }

    public function capture(MetaAdAccount $account, string $key, mixed $previous, mixed $current, $observedAt, array $payload = []): void
    {
        if (! app(AlertAvailability::class)->ready() || $current === null || ! ctype_digit((string) $current) || in_array((string) $current, ['201', '202'], true)) {
            return;
        }
        $customers = $account->relationLoaded('customers') ? $account->customers->pluck('id')->all() : (Schema::hasTable('customer_meta_ad_account')
            ? DB::table('customer_meta_ad_account')->where('meta_ad_account_id', $account->id)->pluck('customer_id')->all() : []);
        if (! $customers && $account->customer_id) {
            $customers = [(int) $account->customer_id];
        }
        AlertObservation::firstOrCreate(['source_key' => $key], [
            'observed_at' => $observedAt ?? now(),
            'data' => [
                'account_id' => $account->id,
                'entity_id' => MetaAdAccountId::normalize((string) $account->meta_account_id),
                'name' => $account->name,
                'customer_ids' => array_values(array_unique($customers)),
                'internally_active' => $account->isActive(),
                'previous' => $previous === null ? null : (string) $previous,
                'current' => (string) $current,
                'disable_reason' => is_scalar($payload['disable_reason'] ?? null) ? (string) $payload['disable_reason'] : null,
            ],
        ]);
    }
}
