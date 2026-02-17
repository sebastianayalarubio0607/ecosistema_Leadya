<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
<<<<<<< HEAD
=======
use Illuminate\Database\Eloquent\Relations\BelongsTo;
>>>>>>> 3ac2fef11dafeeab5dedfae1f504ba67206b2bba

class CrmState extends Model
{
    protected $table = 'crm_state';

<<<<<<< HEAD
    // PK no es int y no es autoincrement
=======
>>>>>>> 3ac2fef11dafeeab5dedfae1f504ba67206b2bba
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'qualification',
<<<<<<< HEAD
    ];

    // Un estado tiene muchos leads
=======
        'meta_event_id',
    ];

>>>>>>> 3ac2fef11dafeeab5dedfae1f504ba67206b2bba
    public function leads()
    {
        return $this->hasMany(Lead::class, 'crm_state', 'id');
    }

<<<<<<< HEAD
    // Un estado pertenece a una qualification
=======
>>>>>>> 3ac2fef11dafeeab5dedfae1f504ba67206b2bba
    public function qualificationModel()
    {
        return $this->belongsTo(Qualification::class, 'qualification', 'id');
    }
<<<<<<< HEAD
=======

    public function metaEvent(): BelongsTo
    {
        return $this->belongsTo(MetaEvent::class, 'meta_event_id');
    }

    
>>>>>>> 3ac2fef11dafeeab5dedfae1f504ba67206b2bba
}
