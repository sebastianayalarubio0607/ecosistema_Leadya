<?php

namespace App\Http\Services\Meta\Subscription\Whatsapp;

use App\Jobs\MetaWhatsappSubscribeJob;
use App\Jobs\MetaWhatsappUnsubscribeJob;
use App\Models\MetaAccessToken;
use App\Models\MetaWhatsapp;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MetaWhatsappSubscriptionService
{
    private const GRAPH_VERSION = 'v26.0';

    private const REQUIRED_PERMISSIONS = [
        'ads_management',
        'ads_read',
        'business_management',
        'whatsapp_business_management',
        'whatsapp_business_messaging',
    ];

    public function syncAll(): array
    {
        $stats = [
            'whatsapps_checked' => 0,
            'subscribe_jobs_dispatched' => 0,
            'unsubscribe_jobs_dispatched' => 0,
            'not_visible' => 0,
        ];

        try {
            $this->validateRequiredPermissions();
        } catch (\Throwable $exception) {
            $stats['not_visible'] = $this->markAllWhatsappsError($exception->getMessage());
            $stats['global_error'] = $exception->getMessage();

            Log::warning('Meta WhatsApp subscription scan skipped', [
                'message' => $exception->getMessage(),
            ]);

            return $stats;
        }

        MetaWhatsapp::query()
            ->orderBy('id')
            ->chunkById(100, function ($whatsapps) use (&$stats) {
                foreach ($whatsapps as $whatsapp) {
                    $result = $this->inspectAndQueue($whatsapp, false);

                    $stats['whatsapps_checked']++;
                    $stats['subscribe_jobs_dispatched'] += (int) ($result['subscribe_job_dispatched'] ?? false);
                    $stats['unsubscribe_jobs_dispatched'] += (int) ($result['unsubscribe_job_dispatched'] ?? false);
                    $stats['not_visible'] += (int) (! ($result['token_can_view_account'] ?? true));
                }
            });

        return $stats;
    }

    public function inspectAndQueue(MetaWhatsapp $whatsapp, bool $validatePermissions = true): array
    {
        if ($validatePermissions) {
            try {
                $this->validateRequiredPermissions();
            } catch (\Throwable $exception) {
                $this->markWhatsappError($whatsapp, $exception->getMessage(), false);

                Log::warning('Meta WhatsApp subscription check skipped', [
                    'meta_whatsapp_id' => $whatsapp->id,
                    'waba_id' => $whatsapp->waba_id,
                    'message' => $exception->getMessage(),
                ]);

                return [
                    'token_can_view_account' => false,
                    'is_subscribed' => false,
                    'subscribe_job_dispatched' => false,
                    'unsubscribe_job_dispatched' => false,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        if (! $this->tokenCanViewWhatsapp($whatsapp)) {
            $subscribeJobDispatched = false;

            if ($whatsapp->status) {
                MetaWhatsappSubscribeJob::dispatch($whatsapp->id);
                $subscribeJobDispatched = true;
            }

            return [
                'token_can_view_account' => false,
                'is_subscribed' => false,
                'subscribe_job_dispatched' => $subscribeJobDispatched,
                'unsubscribe_job_dispatched' => false,
            ];
        }

        $isSubscribed = $this->refreshSubscriptionStatus($whatsapp);
        $subscribeJobDispatched = false;
        $unsubscribeJobDispatched = false;

        if ($whatsapp->status && ! $isSubscribed) {
            MetaWhatsappSubscribeJob::dispatch($whatsapp->id);
            $subscribeJobDispatched = true;
        }

        if (! $whatsapp->status && $isSubscribed) {
            MetaWhatsappUnsubscribeJob::dispatch($whatsapp->id, $whatsapp->waba_id);
            $unsubscribeJobDispatched = true;
        }

        return [
            'token_can_view_account' => true,
            'is_subscribed' => $isSubscribed,
            'subscribe_job_dispatched' => $subscribeJobDispatched,
            'unsubscribe_job_dispatched' => $unsubscribeJobDispatched,
        ];
    }

    public function subscribe(MetaWhatsapp $whatsapp): array
    {
        $this->validateRequiredPermissions();

        if (! $this->tokenCanViewWhatsapp($whatsapp)) {
            Log::warning('Meta WhatsApp subscription will be attempted without prior visibility confirmation', [
                'meta_whatsapp_id' => $whatsapp->id,
                'waba_id' => $whatsapp->waba_id,
            ]);
        }

        $systemToken = $this->resolveSystemUserToken();

        try {
            $response = $this->metaRequest('post', $this->wabaId($whatsapp).'/subscribed_apps', $systemToken);

            $payload = $this->decodeResponse($response);

            if (($payload['success'] ?? false) !== true) {
                throw new RuntimeException('Meta no confirmo la suscripcion WhatsApp: '.json_encode($payload, JSON_UNESCAPED_UNICODE));
            }

            $whatsapp->forceFill([
                'subscribed_apps' => $payload,
                'is_subscribed_to_meta_app' => true,
                'token_can_view_account' => true,
                'subscription_checked_at' => now(),
                'subscription_updated_at' => now(),
                'subscription_last_error' => null,
            ])->saveQuietly();

            Log::info('Meta WhatsApp subscription created', [
                'meta_whatsapp_id' => $whatsapp->id,
                'waba_id' => $whatsapp->waba_id,
                'response' => $payload,
            ]);

            return $payload;
        } catch (\Throwable $exception) {
            $this->markWhatsappError($whatsapp, $exception->getMessage());

            Log::error('Meta WhatsApp subscription failed', [
                'meta_whatsapp_id' => $whatsapp->id,
                'waba_id' => $whatsapp->waba_id,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function unsubscribe(MetaWhatsapp $whatsapp): array
    {
        return $this->unsubscribeByWabaId($whatsapp->waba_id, $whatsapp);
    }

    public function unsubscribeByWabaId(string $wabaId, ?MetaWhatsapp $whatsapp = null): array
    {
        $this->validateRequiredPermissions();

        $systemToken = $this->resolveSystemUserToken();

        try {
            $response = $this->metaRequest('delete', $this->cleanWabaId($wabaId).'/subscribed_apps', $systemToken);
            $payload = $this->decodeResponse($response);

            if (($payload['success'] ?? false) !== true) {
                throw new RuntimeException('Meta no confirmo la cancelacion WhatsApp: '.json_encode($payload, JSON_UNESCAPED_UNICODE));
            }

            if ($whatsapp) {
                $whatsapp->forceFill([
                    'subscribed_apps' => $payload,
                    'is_subscribed_to_meta_app' => false,
                    'subscription_checked_at' => now(),
                    'subscription_updated_at' => now(),
                    'subscription_last_error' => null,
                ])->saveQuietly();
            }

            Log::info('Meta WhatsApp subscription cancelled', [
                'meta_whatsapp_id' => $whatsapp?->id,
                'waba_id' => $wabaId,
                'response' => $payload,
            ]);

            return $payload;
        } catch (\Throwable $exception) {
            if ($whatsapp) {
                $this->markWhatsappError($whatsapp, $exception->getMessage());
            }

            Log::error('Meta WhatsApp unsubscribe failed', [
                'meta_whatsapp_id' => $whatsapp?->id,
                'waba_id' => $wabaId,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function validateRequiredPermissions(): void
    {
        try {
            $systemToken = $this->resolveSystemUserToken();
            $response = $this->metaRequest('get', 'me/permissions', $systemToken);
            $payload = $this->decodeResponse($response);
        } catch (\Throwable $exception) {
            Log::warning('Meta WhatsApp token permissions could not be validated before subscription', [
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        $granted = collect($payload['data'] ?? [])
            ->filter(fn (array $item) => ($item['status'] ?? null) === 'granted')
            ->pluck('permission')
            ->all();

        $missing = array_values(array_diff(self::REQUIRED_PERMISSIONS, $granted));

        if ($missing !== []) {
            Log::warning('Meta WhatsApp token permissions are missing from me/permissions; subscription will be attempted anyway', [
                'missing_permissions' => $missing,
            ]);
        }
    }

    private function tokenCanViewWhatsapp(MetaWhatsapp $whatsapp): bool
    {
        $systemToken = $this->resolveSystemUserToken();
        $appId = $this->resolveMetaAppId();

        try {
            $response = $this->metaRequest('get', $this->wabaId($whatsapp).'/subscribed_apps', $systemToken);
            $payload = $this->decodeResponse($response);
            $isSubscribed = collect($payload['data'] ?? [])
                ->contains(fn (array $item) => (string) $this->subscribedAppId($item) === (string) $appId);

            $whatsapp->forceFill([
                'subscribed_apps' => $payload,
                'is_subscribed_to_meta_app' => $isSubscribed,
                'token_can_view_account' => true,
                'subscription_checked_at' => now(),
                'subscription_last_error' => null,
            ])->saveQuietly();

            Log::info('Meta WhatsApp visibility validated', [
                'meta_whatsapp_id' => $whatsapp->id,
                'waba_id' => $whatsapp->waba_id,
                'response' => $payload,
            ]);

            return true;
        } catch (\Throwable $exception) {
            $whatsapp->forceFill([
                'token_can_view_account' => false,
                'subscription_checked_at' => now(),
                'subscription_last_error' => $exception->getMessage(),
            ])->saveQuietly();

            Log::warning('Meta WhatsApp is not visible for token', [
                'meta_whatsapp_id' => $whatsapp->id,
                'waba_id' => $whatsapp->waba_id,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function refreshSubscriptionStatus(MetaWhatsapp $whatsapp): bool
    {
        $systemToken = $this->resolveSystemUserToken();
        $appId = $this->resolveMetaAppId();

        try {
            $response = $this->metaRequest('get', $this->wabaId($whatsapp).'/subscribed_apps', $systemToken);
            $payload = $this->decodeResponse($response);
            $isSubscribed = collect($payload['data'] ?? [])
                ->contains(fn (array $item) => (string) $this->subscribedAppId($item) === (string) $appId);

            $whatsapp->forceFill([
                'subscribed_apps' => $payload,
                'is_subscribed_to_meta_app' => $isSubscribed,
                'subscription_checked_at' => now(),
                'subscription_last_error' => null,
            ])->saveQuietly();

            return $isSubscribed;
        } catch (\Throwable $exception) {
            $this->markWhatsappError($whatsapp, $exception->getMessage());

            Log::error('Meta WhatsApp subscription validation failed', [
                'meta_whatsapp_id' => $whatsapp->id,
                'waba_id' => $whatsapp->waba_id,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function markWhatsappError(MetaWhatsapp $whatsapp, string $message, ?bool $tokenCanViewAccount = null): void
    {
        $attributes = [
            'subscription_checked_at' => now(),
            'subscription_last_error' => $message,
        ];

        if ($tokenCanViewAccount !== null) {
            $attributes['token_can_view_account'] = $tokenCanViewAccount;
        }

        $whatsapp->forceFill($attributes)->saveQuietly();
    }

    private function markAllWhatsappsError(string $message): int
    {
        return MetaWhatsapp::query()->update([
            'token_can_view_account' => false,
            'subscription_checked_at' => now(),
            'subscription_last_error' => $message,
            'updated_at' => now(),
        ]);
    }

    private function subscribedAppId(array $item): ?string
    {
        $appId = $item['app_id']
            ?? data_get($item, 'whatsapp_business_api_data.id')
            ?? $item['id']
            ?? null;

        if ($appId === null || is_array($appId) || is_object($appId)) {
            return null;
        }

        $appId = trim((string) $appId);

        return $appId === '' ? null : $appId;
    }

    private function resolveMetaAppId(): string
    {
        $appId = MetaAccessToken::query()
            ->where('token_type', MetaAccessToken::TYPE_USER_ACCESS_TOKEN)
            ->where('is_active', true)
            ->whereNotNull('meta_app_id')
            ->latest('id')
            ->value('meta_app_id');

        if (blank($appId)) {
            throw new RuntimeException('No hay meta_app_id activo en meta_access_tokens con token_type=user_access_token para crear la suscripcion WhatsApp.');
        }

        return (string) $appId;
    }

    private function resolveSystemUserToken(): string
    {
        $activeLongLivedTokens = MetaAccessToken::query()
            ->where('is_active', true)
            ->whereNotNull('long_lived_token')
            ->where('long_lived_token', '<>', '');

        $token = (clone $activeLongLivedTokens)
            ->where('token_type', MetaAccessToken::TYPE_SYSTEM_ACCESS_TOKEN)
            ->latest('id')
            ->value('long_lived_token') ?: (clone $activeLongLivedTokens)
                ->latest('id')
                ->value('long_lived_token');

        if (blank($token)) {
            throw new RuntimeException('No hay system_user_token activo en meta_access_tokens.long_lived_token para consultar suscripciones WhatsApp.');
        }

        return (string) $token;
    }

    private function metaRequest(string $method, string $path, string $token, array $params = []): Response
    {
        $request = Http::retry(3, 1000, null, false)
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
            throw new RuntimeException('Meta API error ('.$response->status().'): '.$response->body());
        }

        return is_array($payload) ? $payload : [];
    }

    private function graphUrl(string $path): string
    {
        return 'https://graph.facebook.com/'.self::GRAPH_VERSION.'/'.ltrim($path, '/');
    }

    private function wabaId(MetaWhatsapp $whatsapp): string
    {
        return $this->cleanWabaId((string) $whatsapp->waba_id);
    }

    private function cleanWabaId(string $wabaId): string
    {
        $wabaId = trim($wabaId);

        if ($wabaId === '') {
            throw new RuntimeException('La cuenta WhatsApp no tiene waba_id.');
        }

        return $wabaId;
    }
}
