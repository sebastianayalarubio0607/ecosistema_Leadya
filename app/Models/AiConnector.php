<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiConnector extends Model
{
    public const TOOL_SNAPSHOT = 'get_general_leads_snapshot';
    public const TOOL_SUMMARY = 'get_general_leads_summary';
    public const TOOL_BREAKDOWNS = 'get_general_leads_breakdowns';
    public const TOOL_FUNNELS = 'get_general_leads_funnels';
    public const TOOL_FILTER_OPTIONS = 'get_general_leads_filter_options';
    public const TOOL_COSTS = 'get_general_leads_costs';
    public const TOOL_AD_METRICS = 'get_general_leads_ad_metrics';

    public const AVAILABLE_TOOLS = [
        self::TOOL_SNAPSHOT => 'Snapshot agregado',
        self::TOOL_SUMMARY => 'Resumen agregado',
        self::TOOL_BREAKDOWNS => 'Desgloses agregados',
        self::TOOL_FUNNELS => 'Funnels agregados',
        self::TOOL_FILTER_OPTIONS => 'Opciones de filtros',
        self::TOOL_COSTS => 'Costos agregados',
        self::TOOL_AD_METRICS => 'Metricas de pauta agregadas',
    ];

    public const AD_TOOLS = [
        self::TOOL_COSTS,
        self::TOOL_AD_METRICS,
    ];

    protected $fillable = [
        'name',
        'client_id',
        'client_secret_encrypted',
        'client_secret_hash',
        'client_secret_last_four',
        'customer_id',
        'created_by',
        'is_active',
        'allow_ad_metrics',
        'allowed_tools',
        'allowed_origins',
        'max_requests_per_minute',
        'max_requests_per_day',
        'min_request_interval_seconds',
        'max_date_range_days',
        'cache_ttl_seconds',
        'access_token_ttl_minutes',
        'notes',
        'last_used_at',
        'last_rotated_at',
    ];

    protected $casts = [
        'client_secret_encrypted' => 'encrypted',
        'is_active' => 'boolean',
        'allow_ad_metrics' => 'boolean',
        'allowed_tools' => 'array',
        'allowed_origins' => 'array',
        'last_used_at' => 'datetime',
        'last_rotated_at' => 'datetime',
    ];

    protected $hidden = [
        'client_secret_encrypted',
        'client_secret_hash',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function accessTokens(): HasMany
    {
        return $this->hasMany(AiConnectorAccessToken::class);
    }

    public function queryLogs(): HasMany
    {
        return $this->hasMany(AiConnectorQueryLog::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function defaultTools(): array
    {
        return [
            self::TOOL_SNAPSHOT,
            self::TOOL_SUMMARY,
            self::TOOL_BREAKDOWNS,
            self::TOOL_FUNNELS,
            self::TOOL_FILTER_OPTIONS,
        ];
    }

    public function allowedToolNames(): array
    {
        $tools = $this->allowed_tools ?: self::defaultTools();

        return collect($tools)
            ->filter(fn ($tool) => array_key_exists($tool, self::AVAILABLE_TOOLS))
            ->filter(fn ($tool) => $this->allow_ad_metrics || ! in_array($tool, self::AD_TOOLS, true))
            ->unique()
            ->values()
            ->all();
    }

    public function allowsTool(string $tool): bool
    {
        return in_array($tool, $this->allowedToolNames(), true);
    }

    public function maskedClientSecret(): string
    {
        return '••••••••••••'.$this->client_secret_last_four;
    }
}
