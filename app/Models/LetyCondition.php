<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LetyCondition extends Model
{
    use HasFactory;

    protected $fillable = [
        'integration_id',
        'lety_webhook_id',
        'lead_field',
        'expected_value',
        'order',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'order' => 'integer',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(LetyWebhook::class, 'lety_webhook_id');
    }
}
