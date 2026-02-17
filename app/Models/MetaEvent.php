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

<<<<<<< HEAD
    public function funnels(): HasMany
    {
        return $this->hasMany(Funnel::class, 'meta_event_id');
=======
    public function crmStates(): HasMany
    {
        return $this->hasMany(CrmState::class, 'meta_event_id');
>>>>>>> 3ac2fef11dafeeab5dedfae1f504ba67206b2bba
    }
}
