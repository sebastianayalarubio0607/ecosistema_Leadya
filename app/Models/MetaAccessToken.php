<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetaAccessToken extends Model
{
    public const TYPE_USER_ACCESS_TOKEN = 'user_access_token';
    public const TYPE_APP_ACCESS_TOKEN = 'app_access_token';
    public const TYPE_SYSTEM_ACCESS_TOKEN = 'system_access_token';

    public const SYNC_COLUMNS = [
        'id',
        'token_type',
        'short_lived_token',
        'long_lived_token',
        'meta_app_id',
        'meta_app_secret',
        'expires_in',
        'expires_at',
        'is_active',
        'refresh_last_run_at',
        'last_error',
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        'token_type',
        'short_lived_token',
        'long_lived_token',
        'meta_app_id',
        'meta_app_secret',
        'expires_in',
        'expires_at',
        'is_active',
        'refresh_last_run_at',
        'last_error',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'refresh_last_run_at' => 'datetime',
    ];

    public static function availableTypes(): array
    {
        return [
            self::TYPE_USER_ACCESS_TOKEN,
            self::TYPE_APP_ACCESS_TOKEN,
            self::TYPE_SYSTEM_ACCESS_TOKEN,
        ];
    }

    public static function activeByType(string $type): ?self
    {
        return static::query()
            ->select(self::SYNC_COLUMNS)
            ->where('token_type', $type)
            ->where('is_active', true)
            ->latest('id')
            ->first();
    }

    public static function tokenForType(string $type): ?string
    {
        return static::activeByType($type)?->working_token;
    }

    public function getWorkingTokenAttribute(): ?string
    {
        return $this->long_lived_token ?: $this->short_lived_token;
    }
}
