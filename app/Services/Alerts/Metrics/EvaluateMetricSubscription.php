<?php

namespace App\Services\Alerts\Metrics;

use App\Models\Alerts\AlertMetricRun;
use App\Models\Alerts\AlertMetricSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EvaluateMetricSubscription
{
    public function run(int $id): void
    {
        if (! app(MetricAvailability::class)->ready()) {
            return;
        }
        $at = now();
        $subscription = DB::transaction(function () use ($id, $at) {
            $s = AlertMetricSubscription::with(['monitor', 'customer'])->whereKey($id)->lockForUpdate()->first();
            if (! $s || ! $s->enabled || ! $s->customer?->status || ($s->next_query_at && $s->next_query_at->gt($at))
                || ! app(MetricSchedule::class)->allows($s->settings, 'query', $at)) {
                return null;
            }
            $s->update(['next_query_at' => $at->copy()->addMinutes($s->settings['query_interval']), 'last_status' => 'querying']);

            return $s;
        });
        if (! $subscription) {
            return;
        }
        try {
            $reader = $subscription->monitor->kind === 'leads' ? LeadVolumeReader::class : DirectImpressionReader::class;
            $rows = app($reader)->read($subscription, $at);
            $status = $rows === [] ? 'no_entities' : 'complete';
        } catch (\Throwable $exception) {
            $rows = [];
            $status = 'unavailable';
            // Provider exceptions can contain tokens, URLs or payloads. Never persist their messages.
            Log::warning('Metric alert query unavailable.', ['subscription_id' => $id, 'exception_type' => get_class($exception)]);
        }
        DB::transaction(function () use ($subscription, $rows, $status, $at) {
            $current = AlertMetricSubscription::with(['customer', 'monitor'])->whereKey($subscription->id)->lockForUpdate()->first();
            if (! $current || ! $current->enabled || ! $current->customer?->status || $current->version !== $subscription->version) {
                return;
            }
            AlertMetricRun::create(['subscription_id' => $current->id, 'version' => $current->version, 'status' => $status,
                'summary' => ['entities' => count($rows)], 'checked_at' => $at]);
            $current->update(['last_checked_at' => $at, 'last_status' => $status,
                'last_error' => $status === 'unavailable' ? 'No se obtuvo un reporte completo. Revisa credenciales, permisos y disponibilidad de las cuentas; se reintentará según el horario.' : null]);
            if ($status !== 'unavailable') {
                app(MetricIncidentService::class)->apply($current, $rows, $at);
            }
        });
    }
}
