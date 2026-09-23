<?php

namespace App\Services\Alerts\Metrics;

use App\Models\Alerts\Alert;
use App\Models\Alerts\AlertMetricSubscription;
use App\Models\Alerts\AlertNotification;
use App\Models\Alerts\AlertRecipient;
use App\Services\Alerts\AlertIncidentService;
use Illuminate\Support\Facades\DB;

class MetricNotificationService
{
    public function deliver(int $subscriptionId): void
    {
        DB::transaction(function () use ($subscriptionId) {
            $s = AlertMetricSubscription::with(['users', 'customer'])->whereKey($subscriptionId)->lockForUpdate()->first();
            if (! $s || ! $s->enabled || ! $s->customer?->status || $s->last_status !== 'complete' || ! $s->last_checked_at) {
                return;
            }
            $settings = $s->settings;
            if ($s->last_checked_at->copy()->addHours($settings['fresh_hours'])->lt(now())
                || ! app(MetricSchedule::class)->allows($settings, 'notify', now())) {
                return;
            }
            foreach ($s->states()->whereNotNull('alert_id')->get() as $state) {
                $alert = Alert::whereKey($state->alert_id)->lockForUpdate()->first();
                if (! $alert?->active_key || ! in_array($alert->status, ['open', 'in_progress'], true) || ($alert->metadata['version'] ?? null) !== $s->version) {
                    continue;
                }
                if (! app(MetricSourceEligibility::class)->allows($s->customer, $alert->metadata['rows'])) {
                    continue;
                }
                foreach ($s->users as $user) {
                    $recipient = AlertRecipient::firstOrCreate(['alert_id' => $alert->id, 'user_id' => $user->id], ['requires_response' => $settings['requires_response']]);
                    $recipient = AlertRecipient::whereKey($recipient->id)->lockForUpdate()->firstOrFail();
                    if ($recipient->acknowledged_at || $recipient->dismissed_at || (! $recipient->requires_response && $recipient->read_at)
                        || $recipient->notification_count >= $settings['max_notifications']
                        || ($recipient->last_notified_at && $recipient->last_notified_at->copy()->addMinutes($settings['notify_interval'])->gt(now()))) {
                        continue;
                    }
                    $sequence = $recipient->notification_count + 1;
                    AlertNotification::create(['recipient_id' => $recipient->id, 'rule_id' => null, 'sequence' => $sequence,
                        'channel' => 'internal', 'severity' => $settings['severity'], 'delivered_at' => now(),
                        'rule_snapshot' => ['metric_subscription_id' => $s->id, 'version' => $s->version, 'settings' => $settings, 'measurement' => $alert->metadata['rows']]]);
                    $recipient->update(['notification_count' => $sequence, 'last_notified_at' => now(), 'requires_response' => $recipient->requires_response || $settings['requires_response']]);
                    app(AlertIncidentService::class)->event($alert, 'notified', ['user_id' => $user->id, 'sequence' => $sequence, 'metric_subscription_id' => $s->id]);
                }
            }
        }, 3);
    }
}
