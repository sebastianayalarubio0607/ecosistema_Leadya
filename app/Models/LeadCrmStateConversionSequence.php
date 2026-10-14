<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadCrmStateConversionSequence extends Model
{
    protected $fillable = [
        'lead_id',
        'current_position',
        'total_jobs',
        'status',
        'error_message',
    ];

    protected $casts = [
        'lead_id' => 'integer',
        'current_position' => 'integer',
        'total_jobs' => 'integer',
    ];
}
