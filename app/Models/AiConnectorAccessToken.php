<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiConnectorAccessToken extends Model
{
    protected $fillable = [
        'ai_connector_id',
        'access_token_encrypted',
        'access_token_hash',
        'scopes',
        'expires_at',
        'last_used_at',
        'revoked_at',
    ];

    protected $casts = [
        'access_token_encrypted' => 'encrypted',
        'scopes' => 'array',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token_encrypted',
        'access_token_hash',
    ];

    public function connector(): BelongsTo
    {
        return $this->belongsTo(AiConnector::class, 'ai_connector_id');
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null
            && $this->expires_at !== null
            && $this->expires_at->isFuture()
            && (bool) $this->connector?->is_active;
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?: [], true);
    }
}
