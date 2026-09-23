<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FreshworksOpportunityStage extends Model
{
    protected $fillable = [
        'integration_id',
        'pipeline_id',
        'stage_id',
        'crm_state_id',
        'pipeline_name',
        'stage_name',
    ];
}
