<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaAdInsight extends Model
{
    protected $fillable = [
        'meta_ad_id',

        'account_id',
        'account_name',

        'campaign_id',
        'campaign_name',

        'adset_id',
        'adset_name',

        'ad_id',
        'ad_name',

        'objective',
        'optimization_goal',
        'buying_type',
        'attribution_setting',

        'impressions',
        'reach',
        'frequency',
        'spend',

        'clicks',
        'unique_clicks',
        'inline_link_clicks',

        'ctr',
        'unique_ctr',
        'cpc',
        'cpm',

        'actions',

        'date_start',
        'date_stop',
        'status',
        'purchase_roas',
    ];

    protected $casts = [
        'actions' => 'array',
        'date_start' => 'date',
        'date_stop' => 'date',

        'impressions' => 'integer',
        'reach' => 'integer',
        'clicks' => 'integer',
        'unique_clicks' => 'integer',
        'inline_link_clicks' => 'integer',

        'frequency' => 'decimal:6',
        'spend' => 'decimal:2',
        'ctr' => 'decimal:6',
        'unique_ctr' => 'decimal:6',
        'cpc' => 'decimal:4',
        'cpm' => 'decimal:4',
    ];

    public function ad(): BelongsTo
    {
        return $this->belongsTo(MetaAd::class, 'meta_ad_id');
    }
}
