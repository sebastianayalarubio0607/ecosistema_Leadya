<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignObjective extends Model
{
    protected $fillable = [
        'nombre',
        'estado',
    ];

    protected $casts = [
        'estado' => 'boolean',
    ];

    public function leads()
    {
        return $this->hasMany(Lead::class, 'campaign_objective');
    }
}
