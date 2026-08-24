<?php

namespace App\Http\Services\AiConnectors;

use App\Models\AiConnector;
use App\Models\AiConnectorAccessToken;

class AiConnectorAuthenticationService
{
    public function __construct(private readonly AiConnectorCredentialService $credentials) {}

    public function connectorForClientCredentials(string $clientId, string $clientSecret): ?AiConnector
    {
        $connector = AiConnector::query()
            ->where('client_id', $clientId)
            ->first();

        if (! $connector || ! $connector->is_active) {
            return null;
        }

        $expected = (string) $connector->client_secret_hash;
        $given = $this->credentials->hashClientSecret($clientSecret);

        return hash_equals($expected, $given) ? $connector : null;
    }

    public function accessTokenForBearer(string $bearerToken): ?AiConnectorAccessToken
    {
        $accessToken = AiConnectorAccessToken::query()
            ->with('connector')
            ->where('access_token_hash', $this->credentials->hashAccessToken($bearerToken))
            ->first();

        if (! $accessToken || ! $accessToken->isUsable()) {
            return null;
        }

        if (! $accessToken->hasScope(AiConnectorCredentialService::READ_SCOPE)) {
            return null;
        }

        $accessToken->forceFill(['last_used_at' => now()])->save();
        $accessToken->connector?->forceFill(['last_used_at' => now()])->save();

        return $accessToken;
    }
}
