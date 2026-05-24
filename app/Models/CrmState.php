<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmState extends Model
{
    protected $table = 'crm_state';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'qualification',
        'meta_event_id',
        'unmanaged',
        'google_ads_conversion_action_id',
        'google_ads_conversion_action_name',
        'google_ads_conversion_action_resource_name',
        'google_ads_conversion_enabled',
        'google_ads_conversion_value',
        'google_ads_conversion_currency',
    ];

    protected $casts = [
        'unmanaged' => 'boolean',
        'google_ads_conversion_enabled' => 'boolean',
        'google_ads_conversion_value' => 'decimal:2',
    ];

    public function leads()
    {
        return $this->hasMany(Lead::class, 'crm_state', 'id');
    }

    public function qualificationModel()
    {
        return $this->belongsTo(Qualification::class, 'qualification', 'id');
    }

    public function metaEvent(): BelongsTo
    {
        return $this->belongsTo(MetaEvent::class, 'meta_event_id');
    }

    public function googleAdsConversions(): HasMany
    {
        return $this->hasMany(CrmStateGoogleAdsConversion::class, 'crm_state_id', 'id');
    }
}
