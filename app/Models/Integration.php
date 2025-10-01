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
        'IntegrationType_id',
        'customer_id',
        'url',
        'tokent',
        'status',
    ];

    // Relaciones
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function IntegrationType()
    {
        return $this->belongsTo(IntegrationType::class, 'IntegrationType_id');
    }

    public function leadIntegrations()
    {
        return $this->hasMany(LeadIntegration::class);
    }
}

