<?php

namespace App\Models\Alerts;

use Illuminate\Database\Eloquent\Model;

class AlertType extends Model
{
    protected $guarded = ['id'];

    public $timestamps = false;

    public function subcategory()
    {
        return $this->belongsTo(AlertSubcategory::class, 'subcategory_id');
    }
}
