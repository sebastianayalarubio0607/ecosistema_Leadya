<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    protected $fillable = [
        'code',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function origins(): HasMany
    {
        return $this->hasMany(Origin::class);
    }

    public function siteLinks(): HasMany
    {
        return $this->hasMany(SiteLink::class);
    }

    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(Platform::class)->withTimestamps();
    }
}
