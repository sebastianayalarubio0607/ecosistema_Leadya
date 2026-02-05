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
    ];

    // Relaciones
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
