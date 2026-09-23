<?php

namespace App\Services\Alerts;

use App\Models\Alerts\Alert;
use App\Models\Alerts\AlertNotification;
use App\Models\Alerts\AlertRecipient;
use App\Models\Alerts\AlertRule;
use App\Models\Customer;
use App\Models\MetaAdAccount;
use Illuminate\Support\Facades\DB;

class AlertNotificationService
{
    public function evaluate(): void
    {
        Alert::whereHas('type', fn ($q) => $q->whereNotIn('code', ['metric_impressions', 'metric_leads']))
            ->whereNotNull('customer_id')->whereIn('status', ['open', 'in_progress'])->select(['id', 'customer_id'])->chunkById(100, function ($alerts) {
                foreach ($alerts as $alert) {
                    $this->deliver($alert->id, $alert->customer_id);
                }
            });
    }

    public function deliver(int $alertId, int $customerId): void
    {
        DB::transaction(function () use ($alertId, $customerId) {
            // Rule edits and deliveries share this lock, including new rule inserts.
            if (! Customer::whereKey($customerId)->lockForUpdate()->first()) {
                return;
            }
            $alert = Alert::with('type.subcategory')->whereKey($alertId)->lockForUpdate()->firstOrFail();
            if (in_array($alert->type->code, ['metric_impressions', 'metric_leads'], true)) {
                return;
            }
            if (! in_array($alert->status, ['open', 'in_progress'], true) || ! $this->stillAssigned($alert)) {
                return;
            }
            $rules = AlertRule::with('users')->where('customer_id', $customerId)->get();
            $winner = fn (Alert $item) => $rules->filter(fn ($r) => $r->matches($item))->sortByDesc(fn ($r) => $r->specificity())->first();
            $rule = $winner($alert);
            if (! $rule || ! app(AlertRuleSchedule::class)->allows($rule, now())) {
                return;
            }
            if ($rule->min_alerts > 1) {
                $count = Alert::with('type.subcategory')->where('customer_id', $customerId)->whereIn('status', ['open', 'in_progress'])->get()
                    ->filter(fn ($item) => $winner($item)?->id === $rule->id && $this->stillAssigned($item))->count();
                if ($count < $rule->min_alerts) {
                    return;
                }
            }
            foreach ($rule->users as $user) {
                $recipient = AlertRecipient::firstOrCreate(['alert_id' => $alert->id, 'user_id' => $user->id], ['requires_response' => $rule->requires_response]);
                $recipient = AlertRecipient::whereKey($recipient->id)->lockForUpdate()->firstOrFail();
                if ($recipient->acknowledged_at || $recipient->dismissed_at || (! $recipient->requires_response && $recipient->read_at)
                    || $recipient->notification_count >= $rule->max_notifications
                    || ($recipient->last_notified_at && now()->lt($recipient->last_notified_at->copy()->addMinutes($rule->interval_minutes)))) {
                    continue;
                }
                $sequence = $recipient->notification_count + 1;
                AlertNotification::create([
                    'recipient_id' => $recipient->id, 'rule_id' => $rule->id, 'sequence' => $sequence,
                    'channel' => 'internal', 'severity' => $rule->severity, 'delivered_at' => now(),
                    'rule_snapshot' => array_merge($rule->getAttributes(), ['weekdays' => $rule->weekdays]),
                ]);
                $recipient->update([
                    'requires_response' => $recipient->requires_response || $rule->requires_response,
                    'notification_count' => $sequence, 'last_notified_at' => now(),
                ]);
                app(AlertIncidentService::class)->event($alert, 'notified', ['user_id' => $user->id, 'sequence' => $sequence, 'rule_id' => $rule->id]);
            }
        }, 3);
    }

    private function stillAssigned(Alert $alert): bool
    {
        if ($alert->platform !== 'meta' || $alert->entity_type !== 'ad_account') {
            return true;
        }
        $account = MetaAdAccount::find($alert->meta_ad_account_id);
        if (! $account || ! $account->isActive() || (string) $account->estado_meta === '1') {
            return false;
        }
        $customers = $account->customers()->pluck('customers.id');

        return $customers->isNotEmpty() ? $customers->contains($alert->customer_id) : (int) $account->customer_id === (int) $alert->customer_id;
    }
}
