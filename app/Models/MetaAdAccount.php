<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaAdAccount extends Model
{
    protected $fillable = [
        'customer_id',
        'meta_account_id',
        'name',
        'status',
        'subscribed_apps',
        'is_subscribed_to_meta_app',
        'token_can_view_account',
        'subscription_checked_at',
        'subscription_updated_at',
        'subscription_last_error',
        'estado_meta',
        'estado_meta_nombre',
        'estado_meta_checked_at',
        'estado_meta_payload',
        'estado_meta_last_error',
    ];

    protected $casts = [
        'subscribed_apps' => 'array',
        'is_subscribed_to_meta_app' => 'boolean',
        'token_can_view_account' => 'boolean',
        'subscription_checked_at' => 'datetime',
        'subscription_updated_at' => 'datetime',
        'estado_meta_checked_at' => 'datetime',
        'estado_meta_payload' => 'array',
    ];

    public function isActive(): bool
    {
        return $this->status === 'active' || $this->status === true || $this->status === 1 || $this->status === '1';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(MetaCampaign::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(MetaAdAccountStatusHistory::class);
    }
}
