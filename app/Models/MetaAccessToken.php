<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class MetaAccessToken extends Model
{
    public const TYPE_USER_ACCESS_TOKEN = 'user_access_token';
    public const TYPE_APP_ACCESS_TOKEN = 'app_access_token';
    public const TYPE_SYSTEM_ACCESS_TOKEN = 'system_access_token';
    public const PURPOSE_GENERAL = 'general';
    public const PURPOSE_WHATSAPP = 'whatsapp';

    public const SYNC_COLUMNS = [
        'id',
        'name',
        'customer_id',
        'token_type',
        'purpose',
        'short_lived_token',
        'long_lived_token',
        'meta_app_id',
        'meta_app_secret',
        'meta_business_id',
        'meta_system_user_id',
        'expires_in',
        'expires_at',
        'is_active',
        'is_default',
        'refresh_last_run_at',
        'last_error',
        'permissions_payload',
        'last_validated_at',
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        'name',
        'customer_id',
        'token_type',
        'purpose',
        'short_lived_token',
        'long_lived_token',
        'meta_app_id',
        'meta_app_secret',
        'meta_business_id',
        'meta_system_user_id',
        'expires_in',
        'expires_at',
        'is_active',
        'is_default',
        'refresh_last_run_at',
        'last_error',
        'permissions_payload',
        'last_validated_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'refresh_last_run_at' => 'datetime',
        'permissions_payload' => 'array',
        'last_validated_at' => 'datetime',
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
            ->where(function ($query) {
                $query->whereNull('purpose')
                    ->orWhere('purpose', self::PURPOSE_GENERAL);
            })
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

    public static function availablePurposes(): array
    {
        return [
            self::PURPOSE_GENERAL,
            self::PURPOSE_WHATSAPP,
        ];
    }

    public function scopeWhatsappSystemUsers(Builder $query): Builder
    {
        return $query
            ->where('purpose', self::PURPOSE_WHATSAPP)
            ->where('token_type', self::TYPE_SYSTEM_ACCESS_TOKEN);
    }

    public function scopeActiveWhatsappSystemUsers(Builder $query): Builder
    {
        return $query
            ->whatsappSystemUsers()
            ->where('is_active', true)
            ->where(function ($innerQuery) {
                $innerQuery->whereNotNull('long_lived_token')
                    ->where('long_lived_token', '<>', '')
                    ->orWhere(function ($shortTokenQuery) {
                        $shortTokenQuery->whereNotNull('short_lived_token')
                            ->where('short_lived_token', '<>', '');
                    });
            });
    }

    public function isWhatsappSystemUser(): bool
    {
        return $this->purpose === self::PURPOSE_WHATSAPP
            && $this->token_type === self::TYPE_SYSTEM_ACCESS_TOKEN;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
