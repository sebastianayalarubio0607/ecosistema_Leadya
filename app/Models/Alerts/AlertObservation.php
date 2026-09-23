<?php

namespace App\Models\Alerts;

use Illuminate\Database\Eloquent\Model;

class AlertObservation extends Model
{
    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = ['data' => 'array', 'observed_at' => 'datetime', 'processed_at' => 'datetime'];
}
