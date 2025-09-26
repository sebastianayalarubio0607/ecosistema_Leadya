<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacebookConversionLog extends Model
{
    protected $fillable = [
        
        'customer_id',
        'lead_id',
        'event_name',
        'pixel_id',
        'action_source',
        'event_time',
        'event_source_url',
        'fbp',
        'fbc',
        'client_ip',
        'client_user_agent',
        'test_event_code',
        'user_data',
        'custom_data',
        'request_payload',
        'response_status',
        'response_body',
        'success',
        'attempt',
        'sent_at',
        'error_message',
    ];

    protected $casts = [

        'user_data'       => 'array',
        'custom_data'     => 'array',
        'request_payload' => 'array',
        'response_body'   => 'array',
        'success'         => 'boolean',
        'sent_at'         => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
}
