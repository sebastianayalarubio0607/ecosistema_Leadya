<?php

namespace App\Models\Alerts;

use Illuminate\Database\Eloquent\Model;

class AlertMetricState extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['data' => 'array', 'first_seen_at' => 'datetime', 'checked_at' => 'datetime'];

    public function alert()
    {
        return $this->belongsTo(Alert::class);
    }
}
