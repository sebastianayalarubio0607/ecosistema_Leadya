<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Integration extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'integrationtype_id',
        'customer_id',
        'url',
        'tokent',
        'status',
        'crm_Id_phone',
        'crm_Id_service',
        'crm_Id_fuente',
        'crm_Id_email',
        'public_key',
        'client_id',
        'client_secret',
        'refresh_token',
        'api_domain',
        'scope',
        'token_type',
        'expires_in',
        'token_expires_at',
        'code',
        'territory_id',
        'owner_id',
        'city',
        'lead_source_id',
        'custom_field',
        'url_credenciales',
        'username',
        'password',
        'body',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function integrationtype()
    {
        return $this->belongsTo(Integrationtype::class, 'integrationtype_id');
    }

    public function leadIntegrations()
    {
        return $this->hasMany(LeadIntegration::class);
    }
}
