<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GohighlevelOpportunitySyncRun extends Model
{
    protected $fillable = [
        'integration_id', 'trigger_source', 'status', 'opportunities_checked',
        'leads_updated', 'leads_not_found', 'error_message', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(GohighlevelOpportunitySyncLog::class, 'sync_run_id');
    }
}
