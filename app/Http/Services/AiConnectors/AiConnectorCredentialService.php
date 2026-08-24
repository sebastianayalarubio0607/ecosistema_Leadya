<?php

namespace App\Http\Services\AiConnectors;

use App\Models\AiConnector;
use App\Models\AiConnectorAccessToken;
use Illuminate\Support\Str;

class AiConnectorCredentialService
{
    public const READ_SCOPE = 'general-leads:read';

    public function create(array $attributes): array
    {
        $secret = $this->generateSecret();

        $connector = AiConnector::query()->create(array_merge($attributes, [
            'client_id' => $this->generateClientId(),
            'client_secret_encrypted' => $secret,
            'client_secret_hash' => $this->hashClientSecret($secret),
            'client_secret_last_four' => substr($secret, -4),
            'last_rotated_at' => now(),
        ]));

        return [$connector, $secret];
    }

    public function rotateSecret(AiConnector $connector): string
    {
        $secret = $this->generateSecret();

        $connector->forceFill([
            'client_secret_encrypted' => $secret,
            'client_secret_hash' => $this->hashClientSecret($secret),
            'client_secret_last_four' => substr($secret, -4),
            'last_rotated_at' => now(),
        ])->save();

        $connector->accessTokens()->whereNull('revoked_at')->update([
            'revoked_at' => now(),
        ]);

        return $secret;
    }

    public function issueAccessToken(AiConnector $connector, array $scopes): array
    {
        $accessToken = $this->generateAccessToken();
        $expiresAt = now()->addMinutes(max(5, (int) $connector->access_token_ttl_minutes));

        AiConnectorAccessToken::query()->create([
            'ai_connector_id' => $connector->id,
            'access_token_encrypted' => $accessToken,
            'access_token_hash' => $this->hashAccessToken($accessToken),
            'scopes' => $scopes,
            'expires_at' => $expiresAt,
        ]);

        return [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => now()->diffInSeconds($expiresAt),
            'scope' => implode(' ', $scopes),
        ];
    }

    public function hashClientSecret(string $secret): string
    {
        return hash_hmac('sha256', 'client:'.$secret, (string) config('app.key'));
    }

    public function hashAccessToken(string $token): string
    {
        return hash_hmac('sha256', 'access:'.$token, (string) config('app.key'));
    }

    public function validScopes(?string $scope): array
    {
        $requested = collect(preg_split('/\s+/', trim((string) $scope)) ?: [])
            ->filter()
            ->values();

        if ($requested->isEmpty()) {
            return [self::READ_SCOPE];
        }

        $invalid = $requested->reject(fn ($value) => $value === self::READ_SCOPE);
        if ($invalid->isNotEmpty()) {
            throw new \InvalidArgumentException('El scope solicitado no esta permitido para conectores IA.');
        }

        return $requested->all();
    }

    private function generateClientId(): string
    {
        do {
            $clientId = 'lya_mcp_'.Str::lower(Str::random(24));
        } while (AiConnector::query()->where('client_id', $clientId)->exists());

        return $clientId;
    }

    private function generateSecret(): string
    {
        return 'lya_mcp_secret_'.$this->randomUrlSafe(40);
    }

    private function generateAccessToken(): string
    {
        return 'lya_mcp_access_'.$this->randomUrlSafe(48);
    }

    private function randomUrlSafe(int $bytes): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
