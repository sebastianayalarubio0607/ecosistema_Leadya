<?php

namespace App\Http\Services\Meta\Subscription\Whatsapp;

use App\Jobs\MetaWhatsappSubscribeJob;
use App\Jobs\MetaWhatsappUnsubscribeJob;
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

    public function __construct(
        private readonly MetaWhatsappCredentialResolver $credentials,
    ) {}

    public function syncAll(): array
    {
        $stats = [
            'whatsapps_checked' => 0,
            'subscribe_jobs_dispatched' => 0,
            'unsubscribe_jobs_dispatched' => 0,
            'not_visible' => 0,
        ];

        MetaWhatsapp::query()
            ->with(['customers', 'metaAccessToken'])
            ->orderBy('id')
            ->chunkById(100, function ($whatsapps) use (&$stats) {
                foreach ($whatsapps as $whatsapp) {
                    $result = $this->inspectAndQueue($whatsapp);

                    $stats['whatsapps_checked']++;
                    $stats['subscribe_jobs_dispatched'] += (int) ($result['subscribe_job_dispatched'] ?? false);
                    $stats['unsubscribe_jobs_dispatched'] += (int) ($result['unsubscribe_job_dispatched'] ?? false);
                    $stats['not_visible'] += (int) (! ($result['token_can_view_account'] ?? true));
                }
            });

        return $stats;
    }

    public function inspectAndQueue(
        MetaWhatsapp $whatsapp,
        bool $validatePermissions = true,
        ?int $metaAccessTokenId = null,
        ?int $customerId = null,
    ): array {
        try {
            $credential = $this->credentials->resolve($whatsapp, $customerId, $metaAccessTokenId);

            if ($validatePermissions) {
                $this->validateRequiredPermissions($credential);
            }
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

        if (! $this->tokenCanViewWhatsapp($whatsapp, $credential)) {
            $subscribeJobDispatched = false;

            if ($whatsapp->status) {
                MetaWhatsappSubscribeJob::dispatch(
                    $whatsapp->id,
                    $credential->accessToken->id,
                    $credential->customerId,
                );
                $subscribeJobDispatched = true;
            }

            return [
                'token_can_view_account' => false,
                'is_subscribed' => false,
                'subscribe_job_dispatched' => $subscribeJobDispatched,
                'unsubscribe_job_dispatched' => false,
                'meta_access_token_id' => $credential->accessToken->id,
                'meta_app_id' => $credential->metaAppId,
            ];
        }

        $isSubscribed = $this->refreshSubscriptionStatus($whatsapp, $credential);
        $subscribeJobDispatched = false;
        $unsubscribeJobDispatched = false;

        if ($whatsapp->status && ! $isSubscribed) {
            MetaWhatsappSubscribeJob::dispatch(
                $whatsapp->id,
                $credential->accessToken->id,
                $credential->customerId,
            );
            $subscribeJobDispatched = true;
        }

        if (! $whatsapp->status && $isSubscribed) {
            MetaWhatsappUnsubscribeJob::dispatch(
                $whatsapp->id,
                $whatsapp->waba_id,
                $credential->accessToken->id,
                $credential->customerId,
            );
            $unsubscribeJobDispatched = true;
        }

        return [
            'token_can_view_account' => true,
            'is_subscribed' => $isSubscribed,
            'subscribe_job_dispatched' => $subscribeJobDispatched,
            'unsubscribe_job_dispatched' => $unsubscribeJobDispatched,
            'meta_access_token_id' => $credential->accessToken->id,
            'meta_app_id' => $credential->metaAppId,
        ];
    }

    public function subscribe(
        MetaWhatsapp $whatsapp,
        ?int $metaAccessTokenId = null,
        ?int $customerId = null,
    ): array {
        $credential = $this->credentials->resolve($whatsapp, $customerId, $metaAccessTokenId);
        $this->validateRequiredPermissions($credential);

        if (! $this->tokenCanViewWhatsapp($whatsapp, $credential)) {
            Log::warning('Meta WhatsApp subscription will be attempted without prior visibility confirmation', [
                'meta_whatsapp_id' => $whatsapp->id,
                'waba_id' => $whatsapp->waba_id,
                'meta_access_token_id' => $credential->accessToken->id,
                'meta_app_id' => $credential->metaAppId,
            ]);
        }

        try {
            $response = $this->metaRequest('post', $this->wabaId($whatsapp).'/subscribed_apps', $credential->token);
            $payload = $this->decodeResponse($response);

            if (($payload['success'] ?? false) !== true) {
                throw new RuntimeException('Meta no confirmo la suscripcion WhatsApp: '.json_encode($payload, JSON_UNESCAPED_UNICODE));
            }

            $whatsapp->forceFill($this->subscriptionAttributes(
                credential: $credential,
                payload: $payload,
                isSubscribed: true,
                tokenCanViewAccount: true,
                updated: true,
            ))->saveQuietly();

            Log::info('Meta WhatsApp subscription created', [
                'meta_whatsapp_id' => $whatsapp->id,
                'waba_id' => $whatsapp->waba_id,
                'meta_access_token_id' => $credential->accessToken->id,
                'meta_app_id' => $credential->metaAppId,
                'response' => $payload,
            ]);

            return $payload;
        } catch (\Throwable $exception) {
            $this->markWhatsappError($whatsapp, $exception->getMessage(), null, $credential);

            Log::error('Meta WhatsApp subscription failed', [
                'meta_whatsapp_id' => $whatsapp->id,
                'waba_id' => $whatsapp->waba_id,
                'meta_access_token_id' => $credential->accessToken->id,
                'meta_app_id' => $credential->metaAppId,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function unsubscribe(MetaWhatsapp $whatsapp): array
    {
        return $this->unsubscribeByWabaId(
            $whatsapp->waba_id,
            $whatsapp,
            $whatsapp->subscription_meta_access_token_id ?: $whatsapp->meta_access_token_id,
        );
    }

    public function unsubscribeByWabaId(
        string $wabaId,
        ?MetaWhatsapp $whatsapp = null,
        ?int $metaAccessTokenId = null,
        ?int $customerId = null,
    ): array {
        $credential = $whatsapp
            ? $this->credentials->resolve($whatsapp, $customerId, $metaAccessTokenId)
            : $this->credentials->resolve(new MetaWhatsapp(['waba_id' => $wabaId]), $customerId, $metaAccessTokenId);

        $this->validateRequiredPermissions($credential);

        try {
            $response = $this->metaRequest('delete', $this->cleanWabaId($wabaId).'/subscribed_apps', $credential->token);
            $payload = $this->decodeResponse($response);

            if (($payload['success'] ?? false) !== true) {
                throw new RuntimeException('Meta no confirmo la cancelacion WhatsApp: '.json_encode($payload, JSON_UNESCAPED_UNICODE));
            }

            if ($whatsapp) {
                $whatsapp->forceFill($this->subscriptionAttributes(
                    credential: $credential,
                    payload: $payload,
                    isSubscribed: false,
                    updated: true,
                ))->saveQuietly();
            }

            Log::info('Meta WhatsApp subscription cancelled', [
                'meta_whatsapp_id' => $whatsapp?->id,
                'waba_id' => $wabaId,
                'meta_access_token_id' => $credential->accessToken->id,
                'meta_app_id' => $credential->metaAppId,
                'response' => $payload,
            ]);

            return $payload;
        } catch (\Throwable $exception) {
            if ($whatsapp) {
                $this->markWhatsappError($whatsapp, $exception->getMessage(), null, $credential);
            }

            Log::error('Meta WhatsApp unsubscribe failed', [
                'meta_whatsapp_id' => $whatsapp?->id,
                'waba_id' => $wabaId,
                'meta_access_token_id' => $credential->accessToken->id,
                'meta_app_id' => $credential->metaAppId,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function validateRequiredPermissions(MetaWhatsappCredential $credential): void
    {
        try {
            $response = $this->metaRequest('get', 'me/permissions', $credential->token);
            $payload = $this->decodeResponse($response);
        } catch (\Throwable $exception) {
            Log::warning('Meta WhatsApp token permissions could not be validated before subscription', [
                'meta_access_token_id' => $credential->accessToken->id,
                'meta_app_id' => $credential->metaAppId,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        $credential->accessToken->forceFill([
            'permissions_payload' => $payload,
            'last_validated_at' => now(),
            'last_error' => null,
        ])->saveQuietly();

        $granted = collect($payload['data'] ?? [])
            ->filter(fn (array $item) => ($item['status'] ?? null) === 'granted')
            ->pluck('permission')
            ->all();

        $missing = array_values(array_diff(self::REQUIRED_PERMISSIONS, $granted));

        if ($missing !== []) {
            Log::warning('Meta WhatsApp token permissions are missing from me/permissions; subscription will be attempted anyway', [
                'meta_access_token_id' => $credential->accessToken->id,
                'meta_app_id' => $credential->metaAppId,
                'missing_permissions' => $missing,
            ]);
        }
    }

    private function tokenCanViewWhatsapp(MetaWhatsapp $whatsapp, MetaWhatsappCredential $credential): bool
    {
        try {
            $response = $this->metaRequest('get', $this->wabaId($whatsapp).'/subscribed_apps', $credential->token);
            $payload = $this->decodeResponse($response);
            $isSubscribed = collect($payload['data'] ?? [])
                ->contains(fn (array $item) => (string) $this->subscribedAppId($item) === (string) $credential->metaAppId);

            $whatsapp->forceFill($this->subscriptionAttributes(
                credential: $credential,
                payload: $payload,
                isSubscribed: $isSubscribed,
                tokenCanViewAccount: true,
            ))->saveQuietly();

            Log::info('Meta WhatsApp visibility validated', [
                'meta_whatsapp_id' => $whatsapp->id,
                'waba_id' => $whatsapp->waba_id,
                'meta_access_token_id' => $credential->accessToken->id,
                'meta_app_id' => $credential->metaAppId,
                'response' => $payload,
            ]);

            return true;
        } catch (\Throwable $exception) {
            $this->markWhatsappError($whatsapp, $exception->getMessage(), false, $credential);

            Log::warning('Meta WhatsApp is not visible for token', [
                'meta_whatsapp_id' => $whatsapp->id,
                'waba_id' => $whatsapp->waba_id,
                'meta_access_token_id' => $credential->accessToken->id,
                'meta_app_id' => $credential->metaAppId,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function refreshSubscriptionStatus(MetaWhatsapp $whatsapp, MetaWhatsappCredential $credential): bool
    {
        try {
            $response = $this->metaRequest('get', $this->wabaId($whatsapp).'/subscribed_apps', $credential->token);
            $payload = $this->decodeResponse($response);
            $isSubscribed = collect($payload['data'] ?? [])
                ->contains(fn (array $item) => (string) $this->subscribedAppId($item) === (string) $credential->metaAppId);

            $whatsapp->forceFill($this->subscriptionAttributes(
                credential: $credential,
                payload: $payload,
                isSubscribed: $isSubscribed,
            ))->saveQuietly();

            return $isSubscribed;
        } catch (\Throwable $exception) {
            $this->markWhatsappError($whatsapp, $exception->getMessage(), null, $credential);

            Log::error('Meta WhatsApp subscription validation failed', [
                'meta_whatsapp_id' => $whatsapp->id,
                'waba_id' => $whatsapp->waba_id,
                'meta_access_token_id' => $credential->accessToken->id,
                'meta_app_id' => $credential->metaAppId,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function markWhatsappError(
        MetaWhatsapp $whatsapp,
        string $message,
        ?bool $tokenCanViewAccount = null,
        ?MetaWhatsappCredential $credential = null,
    ): void {
        $attributes = [
            'subscription_checked_at' => now(),
            'subscription_last_error' => $message,
        ];

        if ($credential) {
            $attributes['subscription_meta_access_token_id'] = $credential->accessToken->id;
            $attributes['subscription_meta_app_id'] = $credential->metaAppId;
            $attributes['subscription_token_source'] = $credential->source;
        }

        if ($tokenCanViewAccount !== null) {
            $attributes['token_can_view_account'] = $tokenCanViewAccount;
        }

        $whatsapp->forceFill($attributes)->saveQuietly();
    }

    private function subscriptionAttributes(
        MetaWhatsappCredential $credential,
        array $payload,
        bool $isSubscribed,
        ?bool $tokenCanViewAccount = null,
        bool $updated = false,
    ): array {
        $attributes = [
            'subscribed_apps' => $payload,
            'is_subscribed_to_meta_app' => $isSubscribed,
            'subscription_meta_access_token_id' => $credential->accessToken->id,
            'subscription_meta_app_id' => $credential->metaAppId,
            'subscription_token_source' => $credential->source,
            'subscription_checked_at' => now(),
            'subscription_last_error' => null,
        ];

        if ($updated) {
            $attributes['subscription_updated_at'] = now();
        }

        if ($tokenCanViewAccount !== null) {
            $attributes['token_can_view_account'] = $tokenCanViewAccount;
        }

        return $attributes;
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
