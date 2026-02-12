<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaEvent extends Model
{
    protected $fillable = [
        'nombre',
        'estados',
    ];

    public function crmStates(): HasMany
    {
        return $this->hasMany(CrmState::class, 'meta_event_id');
    }
}
