<?php

namespace App\Models\Alerts;

use Illuminate\Database\Eloquent\Model;

class AlertNotification extends Model
{
    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = ['rule_snapshot' => 'array', 'delivered_at' => 'datetime'];

    public function recipient()
    {
        return $this->belongsTo(AlertRecipient::class, 'recipient_id');
    }
}
