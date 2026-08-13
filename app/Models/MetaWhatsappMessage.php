<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetaWhatsappMessage extends Model
{
    protected $fillable = [
        'waba_id',
        'phone_number_id',
        'wa_id',
        'message_id',
        'message_timestamp',
        'ctwa_clid',
        'source_id',
        'source_url',
        'headline',
        'body',
        'source_type',
        'is_first_message',
        'referral',
        'message',
        'payload',
    ];

    protected $casts = [
        'message_timestamp' => 'datetime',
        'is_first_message' => 'boolean',
        'referral' => 'array',
        'message' => 'array',
        'payload' => 'array',
    ];
}
