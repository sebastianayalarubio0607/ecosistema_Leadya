<?php

namespace App\Http\Services\Meta\Subscription\Account;

use App\Jobs\MetaAdAccountSubscribeJob;
use App\Jobs\MetaAdAccountUnsubscribeJob;
use App\Models\MetaAccessToken;
use App\Models\MetaAdAccount;
use App\Support\MetaAdAccountId;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MetaAccountSubscriptionService
{
    private const REQUIRED_PERMISSIONS = [
        'ads_management',
        'ads_read',
        'business_management',
    ];

    public function syncAll(): array
    {
        $this->validateRequiredPermissions();

        $stats = [
            'accounts_checked' => 0,
            'subscribe_jobs_dispatched' => 0,
            'unsubscribe_jobs_dispatched' => 0,
            'not_visible' => 0,
        ];

        MetaAdAccount::query()
            ->orderBy('id')
            ->chunkById(100, function ($accounts) use (&$stats) {
                foreach ($accounts as $account) {
                    $result = $this->inspectAndQueue($account, false);

                    $stats['accounts_checked']++;
                    $stats['subscribe_jobs_dispatched'] += (int) ($result['subscribe_job_dispatched'] ?? false);
                    $stats['unsubscribe_jobs_dispatched'] += (int) ($result['unsubscribe_job_dispatched'] ?? false);
                    $stats['not_visible'] += (int) (! ($result['token_can_view_account'] ?? true));
                }
            });

        return $stats;
    }

    public function inspectAndQueue(MetaAdAccount $account, bool $validatePermissions = true): array
    {
        if ($validatePermissions) {
            $this->validateRequiredPermissions();
        }

        if (! $this->tokenCanViewAccount($account)) {
            return [
                'token_can_view_account' => false,
                'is_subscribed' => false,
                'subscribe_job_dispatched' => false,
                'unsubscribe_job_dispatched' => false,
            ];
        }

        $isSubscribed = $this->refreshSubscriptionStatus($account);
        $subscribeJobDispatched = false;
        $unsubscribeJobDispatched = false;

        if ($account->isActive() && ! $isSubscribed) {
            MetaAdAccountSubscribeJob::dispatch($account->id);
            $subscribeJobDispatched = true;
        }

        if (! $account->isActive() && $isSubscribed) {
            MetaAdAccountUnsubscribeJob::dispatch($account->id, $account->meta_account_id);
            $unsubscribeJobDispatched = true;
        }

        return [
            'token_can_view_account' => true,
            'is_subscribed' => $isSubscribed,
            'subscribe_job_dispatched' => $subscribeJobDispatched,
            'unsubscribe_job_dispatched' => $unsubscribeJobDispatched,
        ];
    }

    public function subscribe(MetaAdAccount $account): array
    {
        $this->validateRequiredPermissions();

        if (! $this->tokenCanViewAccount($account)) {
            throw new RuntimeException('El token del sistema no puede consultar la cuenta publicitaria '.$account->meta_account_id.'.');
        }

        $systemToken = $this->resolveSystemUserToken();
        $appId = $this->resolveMetaAppId();

        try {
            $response = $this->metaRequest('post', $this->actId($account).'/subscribed_apps', $systemToken, [
                'app_id' => $appId,
            ]);

            $payload = $this->decodeResponse($response);

            if (($payload['success'] ?? false) !== true) {
                throw new RuntimeException('Meta no confirmo la suscripcion de la cuenta publicitaria: '.json_encode($payload, JSON_UNESCAPED_UNICODE));
            }

            $account->forceFill([
                'subscribed_apps' => $payload,
                'is_subscribed_to_meta_app' => true,
                'token_can_view_account' => true,
                'subscription_checked_at' => now(),
                'subscription_updated_at' => now(),
                'subscription_last_error' => null,
            ])->saveQuietly();

            return $payload;
        } catch (\Throwable $exception) {
            $this->markAccountError($account, $exception->getMessage());
            Log::error('Meta ad account subscription failed', [
                'meta_ad_account_id' => $account->id,
                'meta_account_id' => $account->meta_account_id,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function unsubscribe(MetaAdAccount $account): array
    {
        return $this->unsubscribeByMetaAccountId($account->meta_account_id, $account);
    }

    public function unsubscribeByMetaAccountId(string $metaAccountId, ?MetaAdAccount $account = null): array
    {
        $this->validateRequiredPermissions();

        $systemToken = $this->resolveSystemUserToken();

        try {
            $response = $this->metaRequest('delete', $this->actIdFromValue($metaAccountId).'/subscribed_apps', $systemToken);
            $payload = $this->decodeResponse($response);

            if (($payload['success'] ?? false) !== true) {
                throw new RuntimeException('Meta no confirmo la cancelacion de la cuenta publicitaria: '.json_encode($payload, JSON_UNESCAPED_UNICODE));
            }

            if ($account) {
                $account->forceFill([
                    'subscribed_apps' => $payload,
                    'is_subscribed_to_meta_app' => false,
                    'subscription_checked_at' => now(),
                    'subscription_updated_at' => now(),
                    'subscription_last_error' => null,
                ])->saveQuietly();
            }

            return $payload;
        } catch (\Throwable $exception) {
            if ($account) {
                $this->markAccountError($account, $exception->getMessage());
            }

            Log::error('Meta ad account unsubscribe failed', [
                'meta_ad_account_id' => $account?->id,
                'meta_account_id' => $metaAccountId,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function validateRequiredPermissions(): void
    {
        $systemToken = $this->resolveSystemUserToken();
        $response = $this->metaRequest('get', 'me/permissions', $systemToken);
        $payload = $this->decodeResponse($response);
        $granted = collect($payload['data'] ?? [])
            ->filter(fn (array $item) => ($item['status'] ?? null) === 'granted')
            ->pluck('permission')
            ->all();

        $missing = array_values(array_diff(self::REQUIRED_PERMISSIONS, $granted));

        if ($missing !== []) {
            throw new RuntimeException('El token del sistema no tiene permisos requeridos: '.implode(', ', $missing).'.');
        }
    }

    private function tokenCanViewAccount(MetaAdAccount $account): bool
    {
        $systemToken = $this->resolveSystemUserToken();

        try {
            $response = $this->metaRequest('get', $this->actId($account), $systemToken, [
                'fields' => 'id,account_id,name,account_status,disable_reason',
            ]);

            $payload = $this->decodeResponse($response);

            $account->forceFill([
                'token_can_view_account' => true,
                'subscription_checked_at' => now(),
                'subscription_last_error' => null,
            ])->saveQuietly();

            Log::info('Meta ad account visibility validated', [
                'meta_ad_account_id' => $account->id,
                'meta_account_id' => $account->meta_account_id,
                'response' => $payload,
            ]);

            return true;
        } catch (\Throwable $exception) {
            $account->forceFill([
                'token_can_view_account' => false,
                'subscription_checked_at' => now(),
                'subscription_last_error' => $exception->getMessage(),
            ])->saveQuietly();

            Log::warning('Meta ad account is not visible for token', [
                'meta_ad_account_id' => $account->id,
                'meta_account_id' => $account->meta_account_id,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function refreshSubscriptionStatus(MetaAdAccount $account): bool
    {
        $systemToken = $this->resolveSystemUserToken();
        $appId = $this->resolveMetaAppId();

        try {
            $response = $this->metaRequest('get', $this->actId($account).'/subscribed_apps', $systemToken);
            $payload = $this->decodeResponse($response);
            $isSubscribed = collect($payload['data'] ?? [])
                ->contains(fn (array $item) => (string) ($item['app_id'] ?? '') === (string) $appId);

            $account->forceFill([
                'subscribed_apps' => $payload,
                'is_subscribed_to_meta_app' => $isSubscribed,
                'subscription_checked_at' => now(),
                'subscription_last_error' => null,
            ])->saveQuietly();

            return $isSubscribed;
        } catch (\Throwable $exception) {
            $this->markAccountError($account, $exception->getMessage());

            Log::error('Meta ad account subscription validation failed', [
                'meta_ad_account_id' => $account->id,
                'meta_account_id' => $account->meta_account_id,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function markAccountError(MetaAdAccount $account, string $message): void
    {
        $account->forceFill([
            'subscription_checked_at' => now(),
            'subscription_last_error' => $message,
        ])->saveQuietly();
    }

    private function resolveMetaAppId(): string
    {
        $appId = MetaAccessToken::activeByType(MetaAccessToken::TYPE_USER_ACCESS_TOKEN)?->meta_app_id;

        if (blank($appId)) {
            throw new RuntimeException('No hay meta_app_id activo para crear la suscripcion.');
        }

        return (string) $appId;
    }

    private function resolveSystemUserToken(): string
    {
        $token = MetaAccessToken::activeByType(MetaAccessToken::TYPE_SYSTEM_ACCESS_TOKEN)?->long_lived_token
            ?: MetaAccessToken::activeByType(MetaAccessToken::TYPE_USER_ACCESS_TOKEN)?->long_lived_token;

        if (blank($token)) {
            throw new RuntimeException('No hay long_lived_token activo en meta_access_tokens para consultar suscripciones de cuentas publicitarias.');
        }

        return (string) $token;
    }

    private function metaRequest(string $method, string $path, string $token, array $params = []): Response
    {
        $request = Http::retry(3, 1000)
            ->acceptJson()
            ->withToken($token)
            ->timeout(60);

        $url = $this->graphUrl($path);

        return match ($method) {
            'get' => $request->get($url, $params),
            'post' => $request->asForm()->post($url, $params),
            'delete' => $request->asForm()->delete($url, $params),
            default => throw new RuntimeException("Metodo Meta no soportado: {$method}."),
        };
    }

    private function decodeResponse(Response $response): array
    {
        $payload = $response->json();

        if (! $response->successful() || isset($payload['error'])) {
            $body = $response->body();
            throw new RuntimeException('Meta API error ('.$response->status().'): '.$body);
        }

        return is_array($payload) ? $payload : [];
    }

    private function graphUrl(string $path): string
    {
        $version = trim((string) config('services.meta.graph_version', 'v26.0'), '/');
        return 'https://graph.facebook.com/'.$version.'/'.ltrim($path, '/');
    }

    private function actId(MetaAdAccount $account): string
    {
        return $this->actIdFromValue((string) $account->meta_account_id);
    }

    private function actIdFromValue(string $metaAccountId): string
    {
        $metaAccountId = trim($metaAccountId);

        if ($metaAccountId === '') {
            throw new RuntimeException('La cuenta publicitaria no tiene meta_account_id.');
        }

        return MetaAdAccountId::act($metaAccountId);
    }
}
