<?php

namespace App\Models\Alerts;

use Illuminate\Database\Eloquent\Model;

class AlertMetricMonitor extends Model
{
    protected $guarded = ['id'];

    public function subscriptions()
    {
        return $this->hasMany(AlertMetricSubscription::class, 'monitor_id');
    }
}
