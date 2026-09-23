<?php

namespace App\Services\Alerts\Metrics;

use App\Models\Alerts\Alert;
use App\Models\Alerts\AlertMetricState;
use App\Models\Alerts\AlertMetricSubscription;
use App\Models\Alerts\AlertType;
use App\Services\Alerts\AlertIncidentService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class MetricIncidentService
{
    /** Caller holds the subscription lock. No network calls or business-data writes here. */
    public function apply(AlertMetricSubscription $subscription, array $rows, CarbonInterface $at): void
    {
        $settings = $subscription->settings;
        $seen = [];
        $failing = [];
        foreach ($rows as $row) {
            $key = hash('sha256', json_encode([$row['platform'], $row['account'], $settings['level'], $row['entity']]));
            $seen[] = $key;
            $state = AlertMetricState::firstOrCreate(['subscription_id' => $subscription->id, 'scope_key' => $key], [
                'first_seen_at' => $at, 'checked_at' => $at, 'data' => [],
            ]);
            if (($state->data['eligible'] ?? true) === false) {
                $state->first_seen_at = $at;
            }
            $since = $subscription->monitor->kind === 'leads' ? $subscription->activated_at : $state->first_seen_at;
            $warming = $settings['warmup'] && CarbonImmutable::parse($row['window_start'])->lt($since);
            $row += ['threshold' => $settings['minimum'], 'eligible' => true, 'warming' => $warming];
            $state->fill(['checked_at' => $at, 'data' => $row])->save();
            if ($settings['grouping'] === 'separate') {
                $this->incident($subscription, $state, ! $warming && $row['count'] < $settings['minimum'] ? [$row] : [], $at);
            } elseif (! $warming && $row['count'] < $settings['minimum']) {
                $failing[$key] = $row;
            }
        }
        $groupKey = hash('sha256', 'group');
        foreach ($subscription->states()->whereNotIn('scope_key', [...$seen, $groupKey])->get() as $missing) {
            $this->close($missing, 'suspended', $at);
            $missing->update(['data' => array_merge($missing->data, ['eligible' => false]), 'checked_at' => $at]);
        }
        if ($settings['grouping'] === 'together') {
            ksort($failing);
            $state = AlertMetricState::firstOrCreate(['subscription_id' => $subscription->id, 'scope_key' => $groupKey], [
                'first_seen_at' => $at, 'checked_at' => $at, 'data' => [],
            ]);
            $members = array_keys($failing);
            if ($members === [] && array_diff($state->data['members'] ?? [], $seen) !== []) {
                $this->close($state, 'suspended', $at);
            }
            if ($state->alert_id && ($state->data['members'] ?? []) !== $members && $members !== []) {
                $this->close($state, 'superseded', $at);
            }
            $state->update(['checked_at' => $at, 'data' => ['members' => $members]]);
            $this->incident($subscription, $state, array_values($failing), $at);
        }
    }

    private function incident(AlertMetricSubscription $subscription, AlertMetricState $state, array $rows, CarbonInterface $at): void
    {
        if ($rows === []) {
            $this->close($state, 'resolved', $at);

            return;
        }
        $settings = $subscription->settings;
        $fingerprint = hash('sha256', json_encode(['metric', $subscription->id, $subscription->version, $state->scope_key]));
        $alert = $state->alert_id ? Alert::find($state->alert_id) : null;
        $metric = $subscription->monitor->kind === 'leads' ? 'leads' : 'impresiones';
        $message = $subscription->monitor->name.': '.implode('; ', array_map(
            fn ($r) => strtoupper($r['platform']).' · '.$r['name'].': '.$r['count'].' '.$metric.' (mínimo '.$settings['minimum'].')', $rows
        ));
        $metadata = ['metric_subscription_id' => $subscription->id, 'version' => $subscription->version, 'rows' => $rows, 'settings' => $settings];
        if ($alert && $alert->active_key) {
            $alert->update(['message' => $message, 'metadata' => $metadata, 'last_seen_at' => $at]);

            return;
        }
        $alert = Alert::create([
            'customer_id' => $subscription->customer_id, 'customer_name' => $subscription->customer->name,
            'type_id' => AlertType::where('code', 'metric_'.$subscription->monitor->kind)->value('id'),
            'platform' => count(array_unique(array_column($rows, 'platform'))) > 1 ? 'meta_google' : $rows[0]['platform'],
            'entity_type' => $settings['grouping'] === 'together' ? 'metric_group' : $settings['level'],
            'entity_id' => $settings['grouping'] === 'together' ? 'monitor:'.$subscription->id : $rows[0]['entity'],
            'entity_name' => $settings['grouping'] === 'together' ? $subscription->monitor->name : $rows[0]['name'],
            'fingerprint' => $fingerprint, 'active_key' => $fingerprint,
            'previous_alert_id' => Alert::where('fingerprint', $fingerprint)->latest('id')->value('id'),
            'current_state' => 'below_minimum', 'message' => $message, 'severity' => $settings['severity'],
            'status' => 'open', 'metadata' => $metadata, 'detected_at' => $at, 'last_seen_at' => $at,
        ]);
        $state->update(['alert_id' => $alert->id]);
        app(AlertIncidentService::class)->event($alert, 'detected', $metadata, $at);
    }

    public function close(AlertMetricState $state, string $status, CarbonInterface $at): void
    {
        $alert = $state->alert_id ? Alert::whereKey($state->alert_id)->lockForUpdate()->first() : null;
        if ($alert && $alert->active_key) {
            $alert->update(['status' => $status, 'active_key' => null, 'resolved_at' => $status === 'resolved' ? $at : null, 'current_state' => $status]);
            app(AlertIncidentService::class)->event($alert, $status, [], $at);
        }
        if ($state->alert_id) {
            $state->update(['alert_id' => null]);
        }
    }
}
