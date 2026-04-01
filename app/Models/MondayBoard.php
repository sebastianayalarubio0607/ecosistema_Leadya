<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MondayBoard extends Model
{
    use HasFactory;

    protected $fillable = [
        'integration_id',
        'monday_board_id',
        'name',
        'status',
        'condition_lead_field',
        'condition_expected_value',
        'monday_group_id',
        'boards_synced_at',
        'details_synced_at',
    ];

    protected $casts = [
        'status' => 'boolean',
        'boards_synced_at' => 'datetime',
        'details_synced_at' => 'datetime',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(MondayBoardGroup::class);
    }

    public function columns(): HasMany
    {
        return $this->hasMany(MondayBoardColumn::class);
    }

    public function columnMappings(): HasMany
    {
        return $this->hasMany(MondayBoardColumnMapping::class);
    }
}
