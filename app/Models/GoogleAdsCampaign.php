<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoogleAdsCampaign extends Model
{
    protected $fillable = [
        'customer_id',
        'google_ads_customer_id',
        'google_campaign_id',
        'campaign_name',
        'campaign_status',
        'advertising_channel_type',
        'report_date',
        'impressions',
        'clicks',
        'conversions',
        'cost_micros',
        'cost',
        'raw_payload',
    ];

    protected $casts = [
        'report_date' => 'date',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'conversions' => 'decimal:2',
        'cost_micros' => 'integer',
        'cost' => 'decimal:6',
        'raw_payload' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function adGroups(): HasMany
    {
        return $this->hasMany(GoogleAdsAdGroup::class, 'google_campaign_id', 'google_campaign_id')
            ->whereColumn('google_ads_ad_groups.customer_id', 'google_ads_campaigns.customer_id');
    }
}
