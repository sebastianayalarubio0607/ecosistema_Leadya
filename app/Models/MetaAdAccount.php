<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaAdAccount extends Model
{
    protected $fillable = ['customer_id', 'meta_account_id', 'name', 'status'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(MetaCampaign::class);
    }
}
