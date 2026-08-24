<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppEvent extends Model
{
    protected $table = 'whatsapp_events';

    protected $fillable = [
        'event_name',
        'description',
        'funnel_usefulness',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function crmStates(): HasMany
    {
        return $this->hasMany(CrmState::class, 'whatsapp_event_id');
    }
}
