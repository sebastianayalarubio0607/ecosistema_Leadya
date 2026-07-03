<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\FacebookConversionLog;
use App\Models\GoogleAdsAd;
use App\Models\GoogleAdsAdGroup;
use App\Models\GoogleAdsCampaign;
use \App\Models\MetaAdAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
   use HasFactory;
    protected $table = 'customers'; // Nombre de la tabla
    protected $fillable = ['name', 'description', 'token', 'status','fb_pixel_id','fb_access_token', 'id_Gads', 'default_currency_id', 'default_lead_value']; // Campos que se pueden asignar masivamente

    protected $casts = [
        'status' => 'boolean',
        'default_lead_value' => 'decimal:2',
    ];

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

public function defaultCurrency(): BelongsTo
{
    return $this->belongsTo(Currency::class, 'default_currency_id');
}

public function googleAdsCampaigns(): HasMany
{
    return $this->hasMany(GoogleAdsCampaign::class, 'customer_id');
}

public function googleAdsAdGroups(): HasMany
{
    return $this->hasMany(GoogleAdsAdGroup::class, 'customer_id');
}

public function googleAdsAds(): HasMany
{
    return $this->hasMany(GoogleAdsAd::class, 'customer_id');
}

public function getIdGadsAttribute(): ?string
{
    return $this->attributes['id_Gads'] ?? null;
}

public function setIdGadsAttribute(?string $value): void
{
    $this->attributes['id_Gads'] = $value;
}
}
