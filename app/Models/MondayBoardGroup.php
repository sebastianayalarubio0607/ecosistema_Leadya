<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MondayBoardGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'monday_board_id',
        'monday_group_id',
        'title',
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(MondayBoard::class, 'monday_board_id');
    }
}
