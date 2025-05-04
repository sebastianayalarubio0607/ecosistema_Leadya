<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $table = 'customers'; // Nombre de la tabla
    protected $fillable = ['name', 'description', 'token', 'status']; // Campos que se pueden asignar masivamente

    // Método para generar un token hasheado
    public static function generateToken()
    {
        return hash('sha256', bin2hex(random_bytes(32)));
    }
}