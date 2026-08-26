<?php

namespace App\Http\Services\AiConnectors;

use App\Models\AiConnector;
use App\Models\AiConnectorOauthAuthorizationCode;
use App\Models\User;

class AiConnectorOAuthAuthorizationService
{
    public function createCode(
        AiConnector $connector,
        User $user,
        string $redirectUri,
        string $resource,
        array $scopes,
        ?string $codeChallenge,
        ?string $codeChallengeMethod,
    ): string {
        $code = $this->generateCode();

        AiConnectorOauthAuthorizationCode::query()->create([
            'ai_connector_id' => $connector->id,
            'user_id' => $user->id,
            'code_hash' => $this->hashCode($code),
            'redirect_uri' => $redirectUri,
            'resource' => $resource,
            'scopes' => $scopes,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => $codeChallengeMethod,
            'expires_at' => now()->addMinutes(10),
        ]);

        return $code;
    }

    public function consumeCode(
        AiConnector $connector,
        string $code,
        string $redirectUri,
        string $resource,
        ?string $codeVerifier,
    ): ?AiConnectorOauthAuthorizationCode {
        $authorizationCode = AiConnectorOauthAuthorizationCode::query()
            ->with('connector')
            ->where('code_hash', $this->hashCode($code))
            ->first();

        if (! $authorizationCode || ! $authorizationCode->isUsable()) {
            return null;
        }

        if ((int) $authorizationCode->ai_connector_id !== (int) $connector->id) {
            return null;
        }

        if (! hash_equals((string) $authorizationCode->redirect_uri, $redirectUri)) {
            return null;
        }

        if ($authorizationCode->resource && ! hash_equals((string) $authorizationCode->resource, $resource)) {
            return null;
        }

        if (! $this->validPkce($authorizationCode, $codeVerifier)) {
            return null;
        }

        $authorizationCode->forceFill(['used_at' => now()])->save();

        return $authorizationCode;
    }

    public function hashCode(string $code): string
    {
        return hash_hmac('sha256', 'oauth-code:'.$code, (string) config('app.key'));
    }

    private function validPkce(AiConnectorOauthAuthorizationCode $authorizationCode, ?string $codeVerifier): bool
    {
        $challenge = (string) $authorizationCode->code_challenge;
        if ($challenge === '') {
            return true;
        }

        if (! is_string($codeVerifier) || trim($codeVerifier) === '') {
            return false;
        }

        $method = $authorizationCode->code_challenge_method ?: 'plain';
        $expected = $method === 'S256'
            ? rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=')
            : $codeVerifier;

        return hash_equals($challenge, $expected);
    }

    private function generateCode(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    }
}
