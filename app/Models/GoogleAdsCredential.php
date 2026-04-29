<?php

namespace App\Models;

use App\Support\SensitiveValue;
use Illuminate\Database\Eloquent\Model;

class GoogleAdsCredential extends Model
{
    protected $fillable = [
        'mcc_developer_token',
        'client_id',
        'client_secret',
        'refresh_token',
        'access_token',
        'customer_id',
        'mcc_id',
        'access_token_expires_at',
        'is_active',
    ];

    protected $casts = [
        'mcc_developer_token' => 'encrypted',
        'client_id' => 'encrypted',
        'client_secret' => 'encrypted',
        'refresh_token' => 'encrypted',
        'access_token' => 'encrypted',
        'customer_id' => 'encrypted',
        'mcc_id' => 'encrypted',
        'access_token_expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public const SECRET_FIELDS = [
        'mcc_developer_token',
        'client_id',
        'client_secret',
        'refresh_token',
        'access_token',
        'customer_id',
        'mcc_id',
    ];

    public function masked(string $field): string
    {
        return SensitiveValue::mask($this->{$field});
    }
}
