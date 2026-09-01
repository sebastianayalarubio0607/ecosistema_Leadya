<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MetaWhatsapp extends Model
{
    protected $fillable = [
        'meta_access_token_id',
        'waba_id',
        'phone_number_id',
        'wa_id',
        'status',
        'subscribed_apps',
        'is_subscribed_to_meta_app',
        'token_can_view_account',
        'subscription_meta_access_token_id',
        'subscription_meta_app_id',
        'subscription_token_source',
        'subscription_checked_at',
        'subscription_updated_at',
        'subscription_last_error',
    ];

    protected $casts = [
        'status' => 'boolean',
        'subscribed_apps' => 'array',
        'is_subscribed_to_meta_app' => 'boolean',
        'token_can_view_account' => 'boolean',
        'subscription_checked_at' => 'datetime',
        'subscription_updated_at' => 'datetime',
    ];

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_meta_whatsapp')
            ->withPivot('meta_access_token_id')
            ->withTimestamps();
    }

    public function metaAccessToken(): BelongsTo
    {
        return $this->belongsTo(MetaAccessToken::class);
    }

    public function subscriptionMetaAccessToken(): BelongsTo
    {
        return $this->belongsTo(MetaAccessToken::class, 'subscription_meta_access_token_id');
    }
}
