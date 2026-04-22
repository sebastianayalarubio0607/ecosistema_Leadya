<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmState extends Model
{
    protected $table = 'crm_state';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'qualification',
        'meta_event_id',
        'unmanaged',
    ];

    protected $casts = [
        'unmanaged' => 'boolean',
    ];

    public function leads()
    {
        return $this->hasMany(Lead::class, 'crm_state', 'id');
    }

    public function qualificationModel()
    {
        return $this->belongsTo(Qualification::class, 'qualification', 'id');
    }

    public function metaEvent(): BelongsTo
    {
        return $this->belongsTo(MetaEvent::class, 'meta_event_id');
    }

    
}
