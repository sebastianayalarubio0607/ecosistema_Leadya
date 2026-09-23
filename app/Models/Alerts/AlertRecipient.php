<?php

namespace App\Models\Alerts;

use Illuminate\Database\Eloquent\Model;

class AlertRecipient extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['requires_response' => 'boolean', 'read_at' => 'datetime', 'acknowledged_at' => 'datetime', 'dismissed_at' => 'datetime', 'last_notified_at' => 'datetime'];

    public function alert()
    {
        return $this->belongsTo(Alert::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function notifications()
    {
        return $this->hasMany(AlertNotification::class, 'recipient_id');
    }

    public function latestNotification()
    {
        return $this->hasOne(AlertNotification::class, 'recipient_id')->latestOfMany();
    }

    public function comments()
    {
        return $this->hasMany(AlertComment::class, 'recipient_id');
    }

    public function scopePending($query)
    {
        return $query->whereNull('dismissed_at')->where(function ($q) {
            $q->whereNull('read_at')->orWhere(fn ($q) => $q->where('requires_response', true)->whereNull('acknowledged_at'));
        });
    }
}
