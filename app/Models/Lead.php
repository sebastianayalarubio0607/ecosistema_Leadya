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
        'message',
        'fbp',
        'fbc',
    ];

    protected $casts = [
      'status'        => 'boolean',
        'tc'            => 'boolean',
        'age'           => 'integer',
        'fields_custom' => 'array', // si la columna es JSON; si es TEXT, quita este c
    ];


    // Compatibilidad con payloads legacy (acepta last_Name / fields_Custom)
    public function setLastNameAttribute($value)      { $this->attributes['last_name'] = $value; }
    public function setFieldsCustomAttribute($value)  { $this->attributes['fields_custom'] = is_array($value) ? json_encode($value) : $value; }
    public function getFieldsCustomAttribute($value)  { return is_string($value) ? json_decode($value, true) ?? [] : ($value ?? []); }

    
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
