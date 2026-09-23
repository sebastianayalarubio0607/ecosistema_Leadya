<?php

namespace App\Models\Alerts;

use Illuminate\Database\Eloquent\Model;

class AlertEvent extends Model
{
    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = ['data' => 'array', 'occurred_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
