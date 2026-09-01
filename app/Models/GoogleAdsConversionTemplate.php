<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoogleAdsConversionTemplate extends Model
{
    protected $fillable = [
        'name',
        'category',
        'type',
        'google_status',
        'primary_for_goal',
        'click_through_lookback_window_days',
        'default_value',
        'default_currency_code',
        'always_use_default_value',
        'estado_lq',
    ];

    protected $casts = [
        'primary_for_goal' => 'boolean',
        'click_through_lookback_window_days' => 'integer',
        'default_value' => 'decimal:2',
        'always_use_default_value' => 'boolean',
        'estado_lq' => 'boolean',
    ];

    public function scopeEstadoLqActivo(Builder $query): Builder
    {
        return $query->where('estado_lq', true);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(GoogleAdsConversionTemplateHistory::class);
    }

    public function toGoogleCreatePayload(): array
    {
        return [
            'name' => $this->name,
            'category' => $this->category,
            'type' => $this->type,
            'status' => $this->google_status,
            'primaryForGoal' => (bool) $this->primary_for_goal,
            'clickThroughLookbackWindowDays' => (int) $this->click_through_lookback_window_days,
            'valueSettings' => [
                'defaultValue' => (float) $this->default_value,
                'defaultCurrencyCode' => strtoupper((string) $this->default_currency_code),
                'alwaysUseDefaultValue' => (bool) $this->always_use_default_value,
            ],
        ];
    }
}
