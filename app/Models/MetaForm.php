<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaForm extends Model
{
    protected $fillable = [
        'meta_page_id',
        'meta_form_id',
        'name',
        'locale',
        'status',
        'meta_status',
        'raw_payload',
        'last_synced_at',
        'last_error',
    ];

    protected $casts = [
        'status' => 'boolean',
        'raw_payload' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(MetaPage::class, 'meta_page_id');
    }

    public function fieldMappings(): HasMany
    {
        return $this->hasMany(MetaFormFieldMapping::class);
    }
}
