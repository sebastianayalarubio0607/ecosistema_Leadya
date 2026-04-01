<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MondayBoardColumn extends Model
{
    use HasFactory;

    protected $fillable = [
        'monday_board_id',
        'monday_column_id',
        'title',
        'type',
        'settings_json',
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(MondayBoard::class, 'monday_board_id');
    }

    public function mapping(): HasOne
    {
        return $this->hasOne(MondayBoardColumnMapping::class, 'monday_board_column_id');
    }
}
