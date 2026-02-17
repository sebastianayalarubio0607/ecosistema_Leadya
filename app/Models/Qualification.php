<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Qualification extends Model
{
    protected $table = 'qualification';

    protected $fillable = [
        'name',
        'funnel_id',
    ];

    public function crmStates()
    {
        return $this->hasMany(CrmState::class, 'qualification', 'id');
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class, 'funnel_id');
    }
}
