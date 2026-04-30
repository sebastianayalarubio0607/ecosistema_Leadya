<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleAdsFailedJob extends Model
{
    protected $fillable = [
        'lead_id',
        'customer_id',
        'crm_state_id',
        'status',
        'job_class',
        'attempts',
        'payload',
        'response',
        'error_message',
        'exception',
        'failed_at',
        'retried_at',
    ];

    protected $casts = [
        'failed_at' => 'datetime',
        'retried_at' => 'datetime',
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
