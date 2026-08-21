<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaAdAccountStatusHistory extends Model
{
    protected $fillable = [
        'customer_id',
        'meta_ad_account_id',
        'meta_webhook_event_id',
        'meta_account_id',
        'estado_meta_anterior',
        'estado_meta_anterior_nombre',
        'estado_meta_nuevo',
        'estado_meta_nuevo_nombre',
        'changed',
        'query_type',
        'consulted_at',
        'payload',
        'error',
    ];

    protected $casts = [
        'changed' => 'boolean',
        'consulted_at' => 'datetime',
        'payload' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(MetaAdAccount::class, 'meta_ad_account_id');
    }

    public function webhookEvent(): BelongsTo
    {
        return $this->belongsTo(MetaWebhookEvent::class, 'meta_webhook_event_id');
    }
}
