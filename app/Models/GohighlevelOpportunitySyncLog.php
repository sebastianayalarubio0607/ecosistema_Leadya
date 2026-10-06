<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GohighlevelOpportunitySyncLog extends Model
{
    protected $fillable = [
        'sync_run_id', 'integration_id', 'lead_id', 'opportunity_id', 'crm_state_id',
        'previous_crm_state', 'new_crm_state', 'pipeline_id', 'stage_id', 'conversion_channel',
        'state_status', 'facebook_conversion_status', 'google_ads_conversion_status',
        'message', 'conversion_requested_at',
    ];

    protected $casts = [
        'conversion_requested_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(GohighlevelOpportunitySyncRun::class, 'sync_run_id');
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
