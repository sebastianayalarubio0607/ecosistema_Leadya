<?php

namespace App\Http\Services\Meta\Subscription\Whatsapp;

use App\Models\MetaAccessToken;
use App\Models\MetaWhatsapp;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MetaWhatsappCredentialResolver
{
    public function resolve(
        MetaWhatsapp $whatsapp,
        ?int $customerId = null,
        ?int $metaAccessTokenId = null,
    ): MetaWhatsappCredential {
        if ($metaAccessTokenId) {
            return $this->credentialFromTokenId($metaAccessTokenId, 'job', $customerId);
        }

        if ($whatsapp->meta_access_token_id) {
            return $this->credentialFromTokenId((int) $whatsapp->meta_access_token_id, 'waba', $customerId);
        }

        $pivotTokenId = $this->pivotTokenId($whatsapp, $customerId);

        if ($pivotTokenId) {
            return $this->credentialFromTokenId($pivotTokenId, 'customer_waba', $customerId);
        }

        $customerToken = $this->customerToken($whatsapp, $customerId);

        if ($customerToken) {
            return $this->credentialFromToken($customerToken, 'customer', $customerToken->customer_id);
        }

        $defaultToken = MetaAccessToken::query()
            ->activeWhatsappSystemUsers()
            ->where('is_default', true)
            ->whereNull('customer_id')
            ->latest('id')
            ->first();

        if ($defaultToken) {
            return $this->credentialFromToken($defaultToken, 'default');
        }

        $unassignedWhatsappToken = MetaAccessToken::query()
            ->activeWhatsappSystemUsers()
            ->whereNull('customer_id')
            ->latest('id')
            ->first();

        if ($unassignedWhatsappToken) {
            return $this->credentialFromToken($unassignedWhatsappToken, 'whatsapp_unassigned');
        }

        $fallbackToken = MetaAccessToken::activeByType(MetaAccessToken::TYPE_SYSTEM_ACCESS_TOKEN)
            ?: MetaAccessToken::activeByType(MetaAccessToken::TYPE_USER_ACCESS_TOKEN);

        if ($fallbackToken) {
            return $this->credentialFromToken($fallbackToken, 'legacy');
        }

        throw new RuntimeException('No hay system_user_token WhatsApp activo ni token global de respaldo para consultar suscripciones WhatsApp.');
    }

    public function tokenIdsForFailedPayload(array $payload): array
    {
        return [
            'customer_id' => isset($payload['customer_id']) ? (int) $payload['customer_id'] : null,
            'meta_access_token_id' => isset($payload['meta_access_token_id']) ? (int) $payload['meta_access_token_id'] : null,
        ];
    }

    private function credentialFromTokenId(int $tokenId, string $source, ?int $customerId = null): MetaWhatsappCredential
    {
        $token = MetaAccessToken::query()
            ->select(MetaAccessToken::SYNC_COLUMNS)
            ->whereKey($tokenId)
            ->first();

        if (! $token) {
            throw new RuntimeException("No existe el token Meta WhatsApp {$tokenId}.");
        }

        return $this->credentialFromToken($token, $source, $customerId);
    }

    private function credentialFromToken(MetaAccessToken $accessToken, string $source, ?int $customerId = null): MetaWhatsappCredential
    {
        if (! $accessToken->is_active) {
            throw new RuntimeException("El token Meta {$accessToken->id} esta inactivo.");
        }

        $token = $accessToken->working_token;

        if (blank($token)) {
            throw new RuntimeException("El token Meta {$accessToken->id} no tiene access token utilizable.");
        }

        $metaAppId = $accessToken->meta_app_id ?: $this->legacyMetaAppId();

        if (blank($metaAppId)) {
            throw new RuntimeException("El token Meta {$accessToken->id} no tiene meta_app_id para validar la suscripcion WhatsApp.");
        }

        return new MetaWhatsappCredential(
            accessToken: $accessToken,
            token: (string) $token,
            metaAppId: (string) $metaAppId,
            source: $source,
            customerId: $customerId ?: $accessToken->customer_id,
        );
    }

    private function customerToken(MetaWhatsapp $whatsapp, ?int $customerId = null): ?MetaAccessToken
    {
        $customerIds = $customerId ? [$customerId] : $this->customerIdsForWhatsapp($whatsapp);

        if ($customerIds === []) {
            return null;
        }

        return MetaAccessToken::query()
            ->activeWhatsappSystemUsers()
            ->whereIn('customer_id', $customerIds)
            ->latest('id')
            ->first();
    }

    private function pivotTokenId(MetaWhatsapp $whatsapp, ?int $customerId = null): ?int
    {
        $query = DB::table('customer_meta_whatsapp')
            ->where('meta_whatsapp_id', $whatsapp->id)
            ->whereNotNull('meta_access_token_id');

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        $tokenId = $query
            ->orderByDesc('id')
            ->value('meta_access_token_id');

        return $tokenId ? (int) $tokenId : null;
    }

    private function customerIdsForWhatsapp(MetaWhatsapp $whatsapp): array
    {
        if ($whatsapp->relationLoaded('customers')) {
            return $whatsapp->customers
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return DB::table('customer_meta_whatsapp')
            ->where('meta_whatsapp_id', $whatsapp->id)
            ->orderByDesc('id')
            ->pluck('customer_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function legacyMetaAppId(): ?string
    {
        return MetaAccessToken::query()
            ->where('token_type', MetaAccessToken::TYPE_USER_ACCESS_TOKEN)
            ->where(function ($query) {
                $query->whereNull('purpose')
                    ->orWhere('purpose', MetaAccessToken::PURPOSE_GENERAL);
            })
            ->where('is_active', true)
            ->whereNotNull('meta_app_id')
            ->latest('id')
            ->value('meta_app_id');
    }
}
