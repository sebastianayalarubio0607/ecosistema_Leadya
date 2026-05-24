<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmStateGoogleAdsConversion extends Model
{
    protected $fillable = [
        'crm_state_id',
        'customer_id',
        'conversion_action_id',
        'conversion_action_name',
        'conversion_action_resource_name',
    ];

    public function crmState(): BelongsTo
    {
        return $this->belongsTo(CrmState::class, 'crm_state_id', 'id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
