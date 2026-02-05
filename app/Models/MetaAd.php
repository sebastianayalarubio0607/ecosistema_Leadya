<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaAd extends Model
{
    protected $fillable = ['meta_ad_set_id', 'meta_ad_id', 'name', 'status'];

    public function adSet(): BelongsTo
    {
        return $this->belongsTo(MetaAdSet::class, 'meta_ad_set_id');
    }

    public function insights(): HasMany
    {
        return $this->hasMany(MetaAdInsight::class, 'meta_ad_id');
    }
}
