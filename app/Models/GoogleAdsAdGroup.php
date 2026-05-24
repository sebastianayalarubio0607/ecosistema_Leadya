<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoogleAdsAdGroup extends Model
{
    protected $fillable = [
        'customer_id',
        'google_ads_customer_id',
        'google_campaign_id',
        'campaign_name',
        'google_ad_group_id',
        'ad_group_name',
        'ad_group_status',
        'report_date',
        'impressions',
        'clicks',
        'conversions',
        'cost_micros',
        'cost',
        'roas',
        'raw_payload',
    ];

    protected $casts = [
        'report_date' => 'date',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'conversions' => 'decimal:2',
        'cost_micros' => 'integer',
        'cost' => 'decimal:6',
        'roas' => 'float',
        'raw_payload' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(GoogleAdsCampaign::class, 'google_campaign_id', 'google_campaign_id');
    }

    public function ads(): HasMany
    {
        return $this->hasMany(GoogleAdsAd::class, 'google_ad_group_id', 'google_ad_group_id')
            ->whereColumn('google_ads_ads.customer_id', 'google_ads_ad_groups.customer_id');
    }
}
