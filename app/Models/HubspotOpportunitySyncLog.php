<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HubspotOpportunitySyncLog extends Model
{
    protected $fillable = [
        'sync_run_id', 'integration_id', 'lead_id', 'opportunity_id',
        'previous_crm_state', 'new_crm_state', 'pipeline_id', 'stage_id',
        'state_status', 'message',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(HubspotOpportunitySyncRun::class, 'sync_run_id');
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
