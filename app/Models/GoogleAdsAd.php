<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleAdsAd extends Model
{
    protected $fillable = [
        'customer_id',
        'google_ads_customer_id',
        'google_campaign_id',
        'campaign_name',
        'google_ad_group_id',
        'ad_group_name',
        'google_ad_id',
        'ad_status',
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

    public function adGroup(): BelongsTo
    {
        return $this->belongsTo(GoogleAdsAdGroup::class, 'google_ad_group_id', 'google_ad_group_id');
    }
}
