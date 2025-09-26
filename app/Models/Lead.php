<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\FacebookConversionLog;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'name',
        'last_Name',
        'position',
        'city',
        'age',
        'company',
        'country',
        'email',
        'phone',
        'status',
        'tc',
        'fields_Custom',
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
        'customer_id',
        'integration_id',
        'message',
        'fbp',
        'fbc'
    ];

    // Relaciones
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
}
