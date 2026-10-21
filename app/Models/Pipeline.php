<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pipeline extends Model
{
    protected $fillable = ['integration_id', 'external_id', 'name'];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function crmStates(): HasMany
    {
        return $this->hasMany(CrmState::class);
    }
}
