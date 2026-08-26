<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiConnectorOauthAuthorizationCode extends Model
{
    protected $fillable = [
        'ai_connector_id',
        'user_id',
        'code_hash',
        'redirect_uri',
        'resource',
        'scopes',
        'code_challenge',
        'code_challenge_method',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    protected $hidden = [
        'code_hash',
    ];

    public function connector(): BelongsTo
    {
        return $this->belongsTo(AiConnector::class, 'ai_connector_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(): bool
    {
        return $this->used_at === null
            && $this->expires_at !== null
            && $this->expires_at->isFuture()
            && (bool) $this->connector?->is_active;
    }
}
