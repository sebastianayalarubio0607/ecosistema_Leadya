<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'customer_id',
        'integration_id',
        'name',
        'last_name',
        'position',
        'city',
        'age',
        'company',
        'country',
        'email',
        'phone',
        'status',
        'tc',
        'fields_custom',
        'agent',
        'service_city',
        'children',
        'opening_hours',
        'effective_lead',
        'reference',
        'service',
        'remote_ip',
        'page',
        'page_url',
        'campaign_origin',
        'campaign_objective',
        'message',
        'fbp',
        'fbc',
        'plataforma',
        'lenguaje',
        'geo',
        'crm_id',
        'crm_state',
        'meta_id_ad',
        'g_ad',
        'g_clid',
        'gclid',
        'gbraid',
        'wbraid',
        'gad_source',
        'gad_campaignid',
        'google_ad_id',
        'google_adgroup_id',
        'google_campaign_id',
        'matchtype',
        'device',
        'meta_lead_id',
        'meta_page_id',
        'meta_form_id',
        'meta_created_time',
        'meta_payload',
        'value',
        'number_workers',
        'number_locations',
        'campo_numero_1',
        'campo_numero_2',
        'campo_numero_3',
        'campo_numero_4',
        'campo_numero_5',
        'campo_text_1',
        'campo_text_2',
        'campo_text_3',
        'campo_text_4',
        'campo_text_5',
    ];

    protected $casts = [
        'status' => 'boolean',
        'tc' => 'boolean',
        'age' => 'integer',
        'fields_custom' => 'array',
        'value' => 'decimal:2',
        'number_workers' => 'integer',
        'number_locations' => 'integer',
        'campo_numero_1' => 'integer',
        'campo_numero_2' => 'integer',
        'campo_numero_3' => 'integer',
        'campo_numero_4' => 'integer',
        'campo_numero_5' => 'integer',
        'meta_payload' => 'array',
        'meta_created_time' => 'datetime',
    ];

    public function setLastNameAttribute($value)
    {
        $this->attributes['last_name'] = $value;
    }

    public function setFieldsCustomAttribute($value)
    {
        $this->attributes['fields_custom'] = is_array($value) ? json_encode($value) : $value;
    }

    public function getFieldsCustomAttribute($value)
    {
        return is_string($value) ? json_decode($value, true) ?? [] : ($value ?? []);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function integration()
    {
        return $this->belongsTo(Integration::class);
    }

    public function leadIntegrations()
    {
        return $this->hasMany(LeadIntegration::class);
    }

    public function fbConversionLogs()
    {
        return $this->hasMany(FacebookConversionLog::class);
    }

    public function crmState()
    {
        return $this->belongsTo(CrmState::class, 'crm_state', 'id');
    }

    public function metaAd()
    {
        return $this->belongsTo(MetaAd::class, 'meta_id_ad', 'meta_ad_id');
    }

    public function metaCampaign()
    {
        return $this->belongsTo(MetaCampaign::class, 'meta_id_ad', 'meta_campaign_id');
    }

    public function googleAd()
    {
        return $this->belongsTo(GoogleAdsAd::class, 'g_ad', 'google_ad_id');
    }

    public function funnelHistories()
    {
        return $this->hasMany(LeadFunnelHistory::class, 'lead_id');
    }

    public function metaPage()
    {
        return $this->belongsTo(MetaPage::class);
    }

    public function metaForm()
    {
        return $this->belongsTo(MetaForm::class);
    }

    public function campaignOrigin(): BelongsTo
    {
        return $this->belongsTo(Origin::class, 'campaign_origin', 'code');
    }

    public function campaignObjective(): BelongsTo
    {
        return $this->belongsTo(CampaignObjective::class, 'campaign_objective');
    }

    public static function metaMappableFields(): array
    {
        return collect((new static())->getFillable())
            ->reject(fn ($field) => in_array($field, [
                'id',
                'customer_id',
                'integration_id',
                'meta_lead_id',
                'meta_page_id',
                'meta_form_id',
                'meta_created_time',
                'meta_payload',
            ], true))
            ->values()
            ->all();
    }

    public static function integrationMappableFields(): array
    {
        return static::metaMappableFields();
    }
}
