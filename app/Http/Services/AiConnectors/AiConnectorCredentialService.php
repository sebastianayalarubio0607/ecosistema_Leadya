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

    public function issueAccessToken(AiConnector $connector, array $scopes, ?string $resource = null): array
    {
        $accessToken = $this->generateAccessToken();
        $refreshToken = $this->generateRefreshToken();
        $expiresAt = now()->addMinutes(max(5, (int) $connector->access_token_ttl_minutes));
        $refreshExpiresAt = now()->addDays(30);

        AiConnectorAccessToken::query()->create([
            'ai_connector_id' => $connector->id,
            'access_token_encrypted' => $accessToken,
            'access_token_hash' => $this->hashAccessToken($accessToken),
            'refresh_token_encrypted' => $refreshToken,
            'refresh_token_hash' => $this->hashRefreshToken($refreshToken),
            'scopes' => $scopes,
            'resource' => $resource ?: $this->defaultResource(),
            'expires_at' => $expiresAt,
            'refresh_token_expires_at' => $refreshExpiresAt,
        ]);

        return [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => now()->diffInSeconds($expiresAt),
            'refresh_token' => $refreshToken,
            'scope' => implode(' ', $scopes),
        ];
    }

    public function refreshAccessToken(string $refreshToken, AiConnector $connector): ?array
    {
        $existingToken = AiConnectorAccessToken::query()
            ->with('connector')
            ->where('refresh_token_hash', $this->hashRefreshToken($refreshToken))
            ->first();

        if (! $existingToken || ! $existingToken->refreshTokenIsUsable()) {
            return null;
        }

        if ((int) $existingToken->ai_connector_id !== (int) $connector->id) {
            return null;
        }

        $existingToken->forceFill([
            'revoked_at' => now(),
            'refresh_token_revoked_at' => now(),
        ])->save();

        return $this->issueAccessToken(
            $connector,
            $existingToken->scopes ?: [self::READ_SCOPE],
            $existingToken->resource ?: $this->defaultResource(),
        );
    }

    public function hashClientSecret(string $secret): string
    {
        return hash_hmac('sha256', 'client:'.$secret, (string) config('app.key'));
    }

    public function hashAccessToken(string $token): string
    {
        return hash_hmac('sha256', 'access:'.$token, (string) config('app.key'));
    }

    public function hashRefreshToken(string $token): string
    {
        return hash_hmac('sha256', 'refresh:'.$token, (string) config('app.key'));
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

    private function generateRefreshToken(): string
    {
        return 'lya_mcp_refresh_'.$this->randomUrlSafe(56);
    }

    private function defaultResource(): string
    {
        return route('api.ai-connectors.mcp', absolute: true);
    }

    private function randomUrlSafe(int $bytes): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
