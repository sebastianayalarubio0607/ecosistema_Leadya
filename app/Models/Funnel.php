<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Funnel extends Model
{
    protected $fillable = [
        'name',
        'description',
        'status',
        'meta_event_id',
    ];

    public function qualifications(): HasMany
    {
        return $this->hasMany(Qualification::class, 'funnel_id');
    }

    public function metaEvent(): BelongsTo
    {
        return $this->belongsTo(MetaEvent::class, 'meta_event_id');
    }
}
