<?php

namespace App\Models\Alerts;

use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['metadata' => 'array', 'detected_at' => 'datetime', 'last_seen_at' => 'datetime', 'resolved_at' => 'datetime'];

    public function type()
    {
        return $this->belongsTo(AlertType::class, 'type_id');
    }

    public function customer()
    {
        return $this->belongsTo(\App\Models\Customer::class);
    }

    public function recipients()
    {
        return $this->hasMany(AlertRecipient::class);
    }

    public function events()
    {
        return $this->hasMany(AlertEvent::class);
    }

    public function previousAlert()
    {
        return $this->belongsTo(self::class, 'previous_alert_id');
    }
}
