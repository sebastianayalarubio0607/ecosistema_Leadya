<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AtomWebhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'integration_id',
        'name',
        'url',
        'order',
        'active',
        'is_default',
    ];

    protected $casts = [
        'active' => 'boolean',
        'is_default' => 'boolean',
        'order' => 'integer',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(AtomCondition::class);
    }
}
