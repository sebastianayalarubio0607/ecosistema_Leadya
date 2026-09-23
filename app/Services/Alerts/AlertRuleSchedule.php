<?php

namespace App\Services\Alerts;

use App\Models\Alerts\AlertRule;
use Carbon\CarbonInterface;

class AlertRuleSchedule
{
    public function allows(AlertRule $rule, CarbonInterface $at): bool
    {
        if (! $rule->enabled || $rule->channel !== 'internal' || $at->lt($rule->starts_at) || ($rule->ends_at && $at->gt($rule->ends_at))) {
            return false;
        }
        $local = $at->copy()->setTimezone($rule->timezone);
        $day = $local->dayOfWeekIso;
        $time = $local->format('H:i:s');
        $start = $rule->starts_time;
        $end = $rule->ends_time;
        if ($start && $end) {
            if ($start < $end) {
                if ($time < $start || $time >= $end) {
                    return false;
                }
            } else {
                if ($time >= $start) {
                    // The window belongs to the day it starts.
                } elseif ($time < $end) {
                    $day = $local->copy()->subDay()->dayOfWeekIso;
                } else {
                    return false;
                }
            }
        }

        return in_array($day, array_map('intval', $rule->weekdays), true);
    }
}
