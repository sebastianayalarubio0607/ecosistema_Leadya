<?php

namespace App\Models\Alerts;

use Illuminate\Database\Eloquent\Model;

class AlertComment extends Model
{
    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
