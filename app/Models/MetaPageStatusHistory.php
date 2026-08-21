<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaPageStatusHistory extends Model
{
    protected $fillable = [
        'customer_id',
        'meta_page_id',
        'meta_webhook_event_id',
        'meta_page_external_id',
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

    public function page(): BelongsTo
    {
        return $this->belongsTo(MetaPage::class, 'meta_page_id');
    }

    public function webhookEvent(): BelongsTo
    {
        return $this->belongsTo(MetaWebhookEvent::class, 'meta_webhook_event_id');
    }
}
