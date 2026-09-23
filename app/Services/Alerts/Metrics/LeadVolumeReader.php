<?php

namespace App\Services\Alerts\Metrics;

use App\Models\Alerts\AlertMetricSubscription;
use App\Models\Lead;
use Carbon\CarbonInterface;

class LeadVolumeReader
{
    public function read(AlertMetricSubscription $subscription, CarbonInterface $at): array
    {
        [$start, $end] = app(MetricSchedule::class)->window($subscription->settings, $subscription->settings['timezone'], $at);
        $count = Lead::where('customer_id', $subscription->customer_id)
            ->where('created_at', '>=', $start->setTimezone(config('app.timezone')))
            ->where('created_at', '<', $end->setTimezone(config('app.timezone')))->count();

        return [[
            'platform' => 'leads', 'account' => (string) $subscription->customer_id, 'entity' => (string) $subscription->customer_id,
            'name' => 'Leads creados', 'count' => $count, 'window_start' => $start->toIso8601String(), 'window_end' => $end->toIso8601String(),
        ]];
    }
}
