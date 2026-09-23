<?php

namespace App\Models\Alerts;

use Illuminate\Database\Eloquent\Model;

class AlertMetricSubscription extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['enabled' => 'boolean', 'settings' => 'array', 'activated_at' => 'datetime', 'next_query_at' => 'datetime', 'last_checked_at' => 'datetime'];

    public function monitor()
    {
        return $this->belongsTo(AlertMetricMonitor::class, 'monitor_id');
    }

    public function customer()
    {
        return $this->belongsTo(\App\Models\Customer::class);
    }

    public function users()
    {
        return $this->belongsToMany(\App\Models\User::class, 'alert_metric_subscription_user', 'subscription_id', 'user_id');
    }

    public function states()
    {
        return $this->hasMany(AlertMetricState::class, 'subscription_id');
    }
}
