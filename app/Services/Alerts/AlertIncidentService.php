<?php

namespace App\Services\Alerts;

use App\Models\Alerts\Alert;
use App\Models\Alerts\AlertEvent;
use App\Models\Alerts\AlertType;
use App\Models\Customer;

class AlertIncidentService
{
    // The producer supplies a stable identity and structured states, never message matching.
    // Called inside the observation transaction, under its source lock.
    public function observe(AlertType $type, string $platform, string $entityType, array $data, ?string $previous, $at): void
    {
        $open = Alert::where('platform', $platform)->where('entity_type', $entityType)
            ->where('entity_id', $data['entity_id'])->where('type_id', $type->id)
            ->whereNotNull('active_key')->lockForUpdate()->get();

        foreach ($open as $alert) {
            $old = $alert->current_state;
            $alert->current_state = $data['current'];
            $alert->last_seen_at = $at;
            $alert->metadata = ['disable_reason' => $data['disable_reason'] ?? null];
            if (! $data['is_problem']) {
                $alert->status = 'resolved';
                $alert->resolved_at = $at;
                $alert->active_key = null;
                $this->event($alert, 'resolved', ['previous' => $old, 'current' => $data['current']], $at);
            } elseif ($old !== $data['current']) {
                $this->event($alert, 'state_changed', ['previous' => $old, 'current' => $data['current']], $at);
            }
            $alert->save();
        }

        if (! $data['is_problem'] || ! $data['was_healthy'] || ! $data['internally_active']) {
            return;
        }
        foreach (Customer::whereIn('id', $data['customer_ids'])->get(['id', 'name']) as $customer) {
            $fingerprint = hash('sha256', json_encode([$customer->id, $platform, $entityType, $data['entity_id'], $type->code]));
            if (Alert::where('active_key', $fingerprint)->exists()) {
                continue;
            }
            $last = Alert::where('fingerprint', $fingerprint)->latest('id')->value('id');
            $alert = Alert::create([
                'customer_id' => $customer->id, 'customer_name' => $customer->name,
                'type_id' => $type->id, 'platform' => $platform, 'entity_type' => $entityType,
                'entity_id' => $data['entity_id'], 'entity_name' => $data['name'],
                'meta_ad_account_id' => $data['account_id'] ?? null,
                'fingerprint' => $fingerprint, 'active_key' => $fingerprint, 'previous_alert_id' => $last,
                'previous_state' => $previous, 'current_state' => $data['current'],
                'message' => strtr($data['message_template'] ?? '{entity_name}: {previous} → {current} ({customer_name}).', [
                    '{entity_name}' => $data['name'] ?: $data['entity_id'], '{entity_id}' => $data['entity_id'],
                    '{customer_name}' => $customer->name, '{previous}' => $previous ?? '', '{current}' => $data['current'],
                ]),
                'severity' => 'warning', 'status' => 'open',
                'metadata' => ['disable_reason' => $data['disable_reason'] ?? null],
                'detected_at' => $at, 'last_seen_at' => $at,
            ]);
            $this->event($alert, 'detected', ['previous' => $previous, 'current' => $data['current']], $at);
        }
    }

    public function event(Alert $alert, string $kind, array $data = [], $at = null, ?int $userId = null): void
    {
        AlertEvent::create(['alert_id' => $alert->id, 'user_id' => $userId, 'kind' => $kind, 'data' => $data, 'occurred_at' => $at ?? now()]);
    }
}
