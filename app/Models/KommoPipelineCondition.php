<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KommoPipelineCondition extends Model
{
    use HasFactory;

    protected $fillable = [
        'integration_id',
        'lead_field',
        'expected_value',
        'pipeline_id',
        'pipeline_name',
        'status_id',
        'status_name',
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
