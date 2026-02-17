<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmState extends Model
{
    protected $table = 'crm_state';

    // PK no es int y no es autoincrement
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'qualification',
    ];

    // Un estado tiene muchos leads
    public function leads()
    {
        return $this->hasMany(Lead::class, 'crm_state', 'id');
    }

    // Un estado pertenece a una qualification
    public function qualificationModel()
    {
        return $this->belongsTo(Qualification::class, 'qualification', 'id');
    }
}
