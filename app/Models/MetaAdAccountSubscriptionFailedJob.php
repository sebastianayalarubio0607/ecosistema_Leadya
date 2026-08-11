<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetaAdAccountSubscriptionFailedJob extends Model
{
    protected $fillable = [
        'uuid',
        'connection',
        'queue',
        'job_class',
        'action',
        'resource_id',
        'resource_identifier',
        'payload',
        'exception',
        'failed_at',
        'retried_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'failed_at' => 'datetime',
        'retried_at' => 'datetime',
    ];
}
