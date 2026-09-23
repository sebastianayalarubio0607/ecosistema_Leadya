<?php

namespace App\Models\Alerts;

use Illuminate\Database\Eloquent\Model;

class AlertSubcategory extends Model
{
    protected $guarded = ['id'];

    public $timestamps = false;

    public function category()
    {
        return $this->belongsTo(AlertCategory::class, 'category_id');
    }

    public function types()
    {
        return $this->hasMany(AlertType::class, 'subcategory_id');
    }
}
