<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaFormFieldMapping extends Model
{
    protected $fillable = [
        'meta_form_id',
        'meta_field_name',
        'lead_field_name',
        'static_value',
        'is_required',
        'is_active',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(MetaForm::class, 'meta_form_id');
    }
}
