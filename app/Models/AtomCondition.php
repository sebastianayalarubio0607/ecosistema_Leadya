<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AtomCondition extends Model
{
    use HasFactory;

    protected $fillable = [
        'integration_id',
        'atom_webhook_id',
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
        return $this->belongsTo(AtomWebhook::class, 'atom_webhook_id');
    }
}
