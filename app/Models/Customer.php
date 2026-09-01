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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
   use HasFactory;
    protected $table = 'customers'; // Nombre de la tabla
    protected $fillable = ['name', 'description', 'token', 'status','fb_pixel_id','fb_access_token', 'fb_test_event_code', 'Meta_dataset', 'Meta_dataset_id', 'Meta_dataset_token', 'Meta_whatsapp_dataset', 'Meta_whatsapp_dataset_id', 'Meta_whatsapp_dataset_token', 'id_Gads', 'default_currency_id', 'default_lead_value']; // Campos que se pueden asignar masivamente

    protected $casts = [
        'status' => 'boolean',
        'Meta_dataset' => 'boolean',
        'Meta_whatsapp_dataset' => 'boolean',
        'default_lead_value' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::created(function (Customer $customer): void {
            CustomerActionHistory::recordForCustomer(
                $customer,
                'created',
                [],
                $customer->historyValues($customer->getAttributes())
            );
        });

        static::updated(function (Customer $customer): void {
            $changes = collect($customer->getChanges())
                ->except(['updated_at'])
                ->all();

            if ($changes === []) {
                return;
            }

            $oldValues = [];

            foreach (array_keys($changes) as $field) {
                $oldValues[$field] = $customer->getOriginal($field);
            }

            CustomerActionHistory::recordForCustomer(
                $customer,
                'updated',
                $customer->historyValues($oldValues),
                $customer->historyValues($changes)
            );
        });

        static::deleted(function (Customer $customer): void {
            CustomerActionHistory::recordForCustomer(
                $customer,
                'deleted',
                $customer->historyValues($customer->getOriginal()),
                []
            );
        });
    }

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

public function metaWhatsapps(): BelongsToMany
{
    return $this->belongsToMany(MetaWhatsapp::class, 'customer_meta_whatsapp')->withTimestamps();
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

public function actionHistories(): HasMany
{
    return $this->hasMany(CustomerActionHistory::class, 'customer_id');
}

public function googleAdsConversionTemplateHistories(): HasMany
{
    return $this->hasMany(GoogleAdsConversionTemplateHistory::class, 'customer_id');
}

public function getIdGadsAttribute(): ?string
{
    return $this->attributes['id_Gads'] ?? null;
}

public function setIdGadsAttribute(?string $value): void
{
    $this->attributes['id_Gads'] = $value;
}

private function historyValues(array $values): array
{
    return collect($values)
        ->except(['created_at', 'updated_at'])
        ->all();
}
}
