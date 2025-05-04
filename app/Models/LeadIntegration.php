<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadIntegration extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'integration_id',
        'answer',
        'answer_code',
        'status',
    ];

    // Relaciones
    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function integration()
    {
        return $this->belongsTo(Integration::class);
    }
}
