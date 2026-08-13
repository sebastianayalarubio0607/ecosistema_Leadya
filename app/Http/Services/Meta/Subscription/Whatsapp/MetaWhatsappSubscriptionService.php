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
    private const REQUIRED_PERMISSIONS = [
        'ads_management',
        'ads_read',
        'business_management',
        'whatsapp_business_management',
        'whatsapp_business_messaging',
    ];

    public function syncAll(): array
    {
        $this->validateRequiredPermissions();

        $stats = [
            'whatsapps_checked' => 0,
            'subscribe_jobs_dispatched' => 0,
            'unsubscribe_jobs_dispatched' => 0,
            'not_visible' => 0,
        ];

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
            $this->validateRequiredPermissions();
        }

        if (! $this->tokenCanViewWhatsapp($whatsapp)) {
            return [
                'token_can_view_account' => false,
                'is_subscribed' => false,
                'subscribe_job_dispatched' => false,
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
            throw new RuntimeException('El token del sistema no puede consultar la cuenta WhatsApp '.$whatsapp->waba_id.'.');
        }

        $systemToken = $this->resolveSystemUserToken();
        $appId = $this->resolveMetaAppId();

        try {
            $response = $this->metaRequest('post', $this->wabaId($whatsapp).'/subscribed_apps', $systemToken, [
                'app_id' => $appId,
            ]);

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

    private function tokenCanViewWhatsapp(MetaWhatsapp $whatsapp): bool
    {
        $systemToken = $this->resolveSystemUserToken();

        try {
            $response = $this->metaRequest('get', $this->wabaId($whatsapp), $systemToken, [
                'fields' => 'id,name',
            ]);

            $payload = $this->decodeResponse($response);

            $whatsapp->forceFill([
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
                ->contains(fn (array $item) => (string) ($item['app_id'] ?? '') === (string) $appId);

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

    private function markWhatsappError(MetaWhatsapp $whatsapp, string $message): void
    {
        $whatsapp->forceFill([
            'subscription_checked_at' => now(),
            'subscription_last_error' => $message,
        ])->saveQuietly();
    }

    private function resolveMetaAppId(): string
    {
        $appId = MetaAccessToken::activeByType(MetaAccessToken::TYPE_USER_ACCESS_TOKEN)?->meta_app_id
            ?: config('services.meta.app_id');

        if (blank($appId)) {
            throw new RuntimeException('No hay meta_app_id activo para crear la suscripcion WhatsApp.');
        }

        return (string) $appId;
    }

    private function resolveSystemUserToken(): string
    {
        $token = MetaAccessToken::activeByType(MetaAccessToken::TYPE_SYSTEM_ACCESS_TOKEN)?->long_lived_token;

        if (blank($token)) {
            throw new RuntimeException('No hay system_user_token activo en meta_access_tokens para consultar suscripciones WhatsApp.');
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
            throw new RuntimeException('Meta API error ('.$response->status().'): '.$response->body());
        }

        return is_array($payload) ? $payload : [];
    }

    private function graphUrl(string $path): string
    {
        $version = trim((string) config('services.meta.graph_version', 'v26.0'), '/');
        return 'https://graph.facebook.com/'.$version.'/'.ltrim($path, '/');
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
