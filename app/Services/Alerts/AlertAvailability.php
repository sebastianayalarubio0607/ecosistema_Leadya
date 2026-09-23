<?php

namespace App\Services\Alerts;

use Illuminate\Support\Facades\Schema;

class AlertAvailability
{
    public function ready(): bool
    {
        if (! config('alerts.enabled')) {
            return false;
        }
        // Never cache a negative result across migrations in a long-lived worker.
        try {
            return Schema::hasTable('alert_checkpoints');
        } catch (\Throwable) {
            return false;
        }
    }
}
