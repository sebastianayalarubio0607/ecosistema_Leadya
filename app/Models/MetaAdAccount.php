<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MetaAdAccount extends Model
{
    protected $fillable = [
        'customer_id',
        'meta_account_id',
        'name',
        'status',
        'subscribed_apps',
        'is_subscribed_to_meta_app',
        'token_can_view_account',
        'subscription_checked_at',
        'subscription_updated_at',
        'subscription_last_error',
        'estado_meta',
        'estado_meta_nombre',
        'estado_meta_checked_at',
        'estado_meta_payload',
        'estado_meta_last_error',
    ];

    protected $casts = [
        'subscribed_apps' => 'array',
        'is_subscribed_to_meta_app' => 'boolean',
        'token_can_view_account' => 'boolean',
        'subscription_checked_at' => 'datetime',
        'subscription_updated_at' => 'datetime',
        'estado_meta_checked_at' => 'datetime',
        'estado_meta_payload' => 'array',
    ];

    public function isActive(): bool
    {
        return $this->status === 'active' || $this->status === true || $this->status === 1 || $this->status === '1';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_meta_ad_account')
            ->withPivot('is_default_for_whatsapp_leads')
            ->withTimestamps();
    }

    public function defaultWhatsappLeadCustomer(): BelongsToMany
    {
        return $this->customers()->wherePivot('is_default_for_whatsapp_leads', true);
    }

    public function syncCustomersWithWhatsappDefault(array $customerIds, ?int $defaultCustomerId = null): ?int
    {
        $customerIds = collect($customerIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($customerIds->isEmpty()) {
            $this->customers()->sync([]);

            return null;
        }

        if ($customerIds->count() === 1) {
            $defaultCustomerId = $customerIds->first();
        }

        if ($defaultCustomerId === null || ! $customerIds->contains((int) $defaultCustomerId)) {
            $defaultCustomerId = $customerIds->first();
        }

        $syncPayload = $customerIds
            ->mapWithKeys(fn (int $customerId) => [
                $customerId => [
                    'is_default_for_whatsapp_leads' => $customerId === (int) $defaultCustomerId,
                ],
            ])
            ->all();

        $this->customers()->sync($syncPayload);

        return (int) $defaultCustomerId;
    }

    public function ensureWhatsappDefaultCustomer(?int $preferredCustomerId = null): ?int
    {
        if (! Schema::hasTable('customer_meta_ad_account')) {
            return $this->customer_id ? (int) $this->customer_id : null;
        }

        $pivotRows = DB::table('customer_meta_ad_account')
            ->where('meta_ad_account_id', $this->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'customer_id', 'is_default_for_whatsapp_leads']);

        if ($pivotRows->isEmpty()) {
            return null;
        }

        $customerIds = $pivotRows->pluck('customer_id')->map(fn ($id) => (int) $id);
        $defaultCustomerId = $preferredCustomerId && $customerIds->contains((int) $preferredCustomerId)
            ? (int) $preferredCustomerId
            : (int) (
                $pivotRows->firstWhere('is_default_for_whatsapp_leads', true)?->customer_id
                ?? $pivotRows->first()->customer_id
            );

        DB::table('customer_meta_ad_account')
            ->where('meta_ad_account_id', $this->id)
            ->update([
                'is_default_for_whatsapp_leads' => false,
                'updated_at' => now(),
            ]);

        DB::table('customer_meta_ad_account')
            ->where('meta_ad_account_id', $this->id)
            ->where('customer_id', $defaultCustomerId)
            ->update([
                'is_default_for_whatsapp_leads' => true,
                'updated_at' => now(),
            ]);

        return $defaultCustomerId;
    }

    public function resolveWhatsappLeadCustomerId(): ?int
    {
        return $this->ensureWhatsappDefaultCustomer();
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(MetaCampaign::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(MetaAdAccountStatusHistory::class);
    }
}
