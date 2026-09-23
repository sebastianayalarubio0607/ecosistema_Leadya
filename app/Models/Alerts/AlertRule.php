<?php

namespace App\Models\Alerts;

use Illuminate\Database\Eloquent\Model;

class AlertRule extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['enabled' => 'boolean', 'requires_response' => 'boolean', 'weekdays' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function customer()
    {
        return $this->belongsTo(\App\Models\Customer::class);
    }

    public function category()
    {
        return $this->belongsTo(AlertCategory::class, 'category_id');
    }

    public function subcategory()
    {
        return $this->belongsTo(AlertSubcategory::class, 'subcategory_id');
    }

    public function type()
    {
        return $this->belongsTo(AlertType::class, 'type_id');
    }

    public function users()
    {
        return $this->belongsToMany(\App\Models\User::class, 'alert_rule_user');
    }

    public function matches(Alert $alert): bool
    {
        return (int) $this->customer_id === (int) $alert->customer_id
            && (int) $this->category_id === (int) $alert->type->subcategory->category_id
            && (! $this->subcategory_id || (int) $this->subcategory_id === (int) $alert->type->subcategory_id)
            && (! $this->type_id || (int) $this->type_id === (int) $alert->type_id);
    }

    public function specificity(): int
    {
        return $this->type_id ? 3 : ($this->subcategory_id ? 2 : 1);
    }
}
