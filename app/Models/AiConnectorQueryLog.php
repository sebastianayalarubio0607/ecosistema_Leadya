<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiConnectorQueryLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'ai_connector_id',
        'tool_name',
        'query_hash',
        'status',
        'cache_hit',
        'duration_ms',
        'ip_address',
        'user_agent',
        'error_message',
        'created_at',
    ];

    protected $casts = [
        'cache_hit' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function connector(): BelongsTo
    {
        return $this->belongsTo(AiConnector::class, 'ai_connector_id');
    }
}
