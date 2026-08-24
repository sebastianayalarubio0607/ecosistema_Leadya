<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MetaWebhookEvent extends Model
{
    protected $fillable = [
        'event_uuid',
        'event_hash',
        'product',
        'object',
        'field',
        'app_id',
        'entry_id',
        'page_id',
        'account_id',
        'leadgen_id',
        'form_id',
        'ad_id',
        'adset_id',
        'campaign_id',
        'sender_id',
        'recipient_id',
        'meta_event_time',
        'received_at',
        'processing_status',
        'processing_error',
        'value',
        'referral',
        'payload',
        'request_headers',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'value' => 'array',
        'referral' => 'array',
        'payload' => 'array',
        'meta_event_time' => 'datetime',
        'received_at' => 'datetime',
        'request_headers' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            if (blank($event->event_uuid)) {
                $event->event_uuid = (string) Str::uuid();
            }
        });
    }
}
