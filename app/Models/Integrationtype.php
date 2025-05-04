<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrationType extends Model
{
    use HasFactory;

    // <<< Esta línea es OBLIGATORIA en tu caso
    protected $table = 'integrationtypes';

    protected $fillable = [
        'name',
        'description',
        'status',
    ];
}
