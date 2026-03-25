<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\FacebookConversionLog;
use \App\Models\MetaAdAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
   use HasFactory;
    protected $table = 'customers'; // Nombre de la tabla
    protected $fillable = ['name', 'description', 'token', 'status','fb_pixel_id','fb_access_token']; // Campos que se pueden asignar masivamente

    // Método para generar un token hasheado
    public static function generateToken()
    {
        return hash('sha256', bin2hex(random_bytes(32)));
    }

    public function fbConversionLogs()
    {
        return $this->hasMany(FacebookConversionLog::class);
    }

public function metaAdAccounts()
{
    return $this->hasMany(MetaAdAccount::class, 'customer_id');
}

public function metaPages(): HasMany
{
    return $this->hasMany(MetaPage::class, 'customer_id');
}

public function metaAccessTokens(): HasMany
{
    return $this->hasMany(MetaAccessToken::class, 'customer_id');
}
}
