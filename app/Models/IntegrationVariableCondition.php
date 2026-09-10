<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationVariableCondition extends Model
{
    use HasFactory;

    protected $fillable = [
        'integration_id',
        'target_variable',
        'source_type',
        'source_key',
        'operator',
        'comparison_value',
        'result_value',
        'result_type',
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
}
