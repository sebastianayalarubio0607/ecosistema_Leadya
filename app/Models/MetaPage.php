<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaPage extends Model
{
    protected $fillable = [
        'customer_id',
        'meta_page_id',
        'name',
        'page_access_token',
        'status',
        'last_synced_at',
        'last_token_refresh_at',
        'last_error',
        'leadgen',
        'is_leadgen_subscribed',
        'subscription_checked_at',
        'subscription_updated_at',
        'subscription_last_error',
    ];

    protected $casts = [
        'status' => 'boolean',
        'leadgen' => 'array',
        'is_leadgen_subscribed' => 'boolean',
        'last_synced_at' => 'datetime',
        'last_token_refresh_at' => 'datetime',
        'subscription_checked_at' => 'datetime',
        'subscription_updated_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function forms(): HasMany
    {
        return $this->hasMany(MetaForm::class);
    }
}
