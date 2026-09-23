<?php

namespace App\Services\Alerts\Metrics;

use App\Models\Alerts\AlertRule;
use App\Services\Alerts\AlertRuleSchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class MetricSchedule
{
    public function allows(array $settings, string $phase, CarbonInterface $at): bool
    {
        return app(AlertRuleSchedule::class)->allows(new AlertRule([
            'enabled' => true, 'channel' => 'internal', 'starts_at' => '2000-01-01',
            'timezone' => $settings['timezone'], 'weekdays' => $settings[$phase.'_days'],
            'starts_time' => $settings[$phase.'_start'].':00', 'ends_time' => $settings[$phase.'_end'].':00',
        ]), $at);
    }

    public function window(array $settings, string $timezone, CarbonInterface $at): array
    {
        if (($settings['window_mode'] ?? 'hours') === 'days') {
            $end = CarbonImmutable::instance($at)->setTimezone($timezone)->subHours($settings['lag_hours'])->startOfDay();

            return [$end->subDays(intdiv($settings['window_hours'], 24)), $end];
        }
        $end = CarbonImmutable::instance($at)->setTimezone($timezone)->startOfHour()->subHours($settings['lag_hours']);

        return [$end->subHours($settings['window_hours']), $end];
    }
}
