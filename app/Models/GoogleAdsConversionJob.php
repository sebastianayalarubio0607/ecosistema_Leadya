<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleAdsConversionJob extends Model
{
    protected $fillable = [
        'lead_id',
        'customer_id',
        'crm_state_id',
        'status',
        'conversion_action_id',
        'conversion_action_resource_name',
        'order_id',
        'click_identifier_type',
        'click_identifier_value',
        'attempts',
        'payload',
        'response',
        'success',
        'partial_failure',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'success' => 'boolean',
        'partial_failure' => 'boolean',
        'processed_at' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function crmState()
    {
        return $this->belongsTo(CrmState::class, 'crm_state_id', 'id');
    }

    public function getPayloadDataAttribute(): array
    {
        return $this->decodeJson($this->payload);
    }

    public function getResponseDataAttribute(): array
    {
        return $this->decodeJson($this->response);
    }

    protected function decodeJson(?string $value): array
    {
        if (! $value) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
