<?php

namespace App\Models\Alerts;

use Illuminate\Database\Eloquent\Model;

class AlertMetricRun extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['summary' => 'array', 'checked_at' => 'datetime'];
}
