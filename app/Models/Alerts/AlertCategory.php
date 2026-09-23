<?php

namespace App\Models\Alerts;

use Illuminate\Database\Eloquent\Model;

class AlertCategory extends Model
{
    protected $guarded = ['id'];

    public $timestamps = false;

    public function subcategories()
    {
        return $this->hasMany(AlertSubcategory::class, 'category_id');
    }
}
