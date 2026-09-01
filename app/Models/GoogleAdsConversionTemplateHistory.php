<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class GoogleAdsConversionTemplateHistory extends Model
{
    protected $fillable = [
        'google_ads_conversion_template_id',
        'customer_id',
        'user_id',
        'google_ads_customer_id',
        'template_name',
        'action',
        'actor_type',
        'actor_id',
        'actor_name',
        'old_values',
        'new_values',
        'payload',
        'response',
        'request_id',
        'success',
        'error_message',
        'consulted_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'payload' => 'array',
        'response' => 'array',
        'success' => 'boolean',
        'consulted_at' => 'datetime',
    ];

    public static function record(array $attributes): ?self
    {
        if (! Schema::hasTable('google_ads_conversion_template_histories')) {
            return null;
        }

        $actor = $attributes['actor'] ?? CustomerActionHistory::resolveActor();
        unset($attributes['actor']);

        $template = $attributes['template'] ?? null;
        unset($attributes['template']);

        if ($template instanceof GoogleAdsConversionTemplate) {
            $attributes['google_ads_conversion_template_id'] ??= $template->id;
            $attributes['template_name'] ??= $template->name;
        }

        $attributes['actor_type'] ??= $actor['type'] ?? 'system';
        $attributes['actor_id'] ??= $actor['id'] ?? null;
        $attributes['actor_name'] ??= $actor['name'] ?? null;
        $attributes['user_id'] ??= (($attributes['actor_type'] ?? null) === 'user' ? ($attributes['actor_id'] ?? null) : null);
        $attributes['consulted_at'] ??= now();

        return self::create($attributes);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(GoogleAdsConversionTemplate::class, 'google_ads_conversion_template_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
