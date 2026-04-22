<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Integration extends Model
{
    use HasFactory;

    protected $casts = [
        'status' => 'boolean',
        'token_expires_at' => 'datetime',
        'disable_integration_id_crm_prefix' => 'boolean',
    ];

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
        'disable_integration_id_crm_prefix',
        'crm_id_prefix',
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
        'url_consulta_lead',
        'url_negocio',
        'url_creacionlead',
        'dealname',
        'dealstage',
    ];

    public function crmIdPrefix(): string
    {
        $manualPrefix = trim((string) $this->crm_id_prefix);

        if ($this->disable_integration_id_crm_prefix && $manualPrefix !== '') {
            return $manualPrefix;
        }

        return (string) $this->id;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function integrationtype(): BelongsTo
    {
        return $this->belongsTo(Integrationtype::class, 'integrationtype_id');
    }

    public function leadIntegrations(): HasMany
    {
        return $this->hasMany(LeadIntegration::class);
    }

    public function mondayBoards(): HasMany
    {
        return $this->hasMany(MondayBoard::class);
    }
}
