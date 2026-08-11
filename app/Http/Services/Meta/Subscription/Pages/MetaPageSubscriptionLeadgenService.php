<?php

namespace App\Http\Services\Meta\Subscription\Pages;

use App\Jobs\MetaPageSubscribeLeadgenJob;
use App\Jobs\MetaPageUnsubscribeLeadgenJob;
use App\Models\MetaAccessToken;
use App\Models\MetaPage;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MetaPageSubscriptionLeadgenService
{
    public function syncAll(): array
    {
        $stats = [
            'pages_checked' => 0,
            'subscribe_jobs_dispatched' => 0,
            'unsubscribe_jobs_dispatched' => 0,
            'pages_with_error' => 0,
        ];

        MetaPage::query()
            ->orderBy('id')
            ->chunkById(100, function ($pages) use (&$stats) {
                foreach ($pages as $page) {
                    $result = $this->inspectAndQueue($page);

                    $stats['pages_checked']++;
                    $stats['subscribe_jobs_dispatched'] += (int) ($result['subscribe_job_dispatched'] ?? false);
                    $stats['unsubscribe_jobs_dispatched'] += (int) ($result['unsubscribe_job_dispatched'] ?? false);
                    $stats['pages_with_error'] += (int) filled($result['error'] ?? null);
                }
            });

        return $stats;
    }

    public function inspectAndQueue(MetaPage $page): array
    {
        $isSubscribed = $this->refreshSubscriptionStatus($page);
        $subscribeJobDispatched = false;
        $unsubscribeJobDispatched = false;

        if ($page->status && ! $isSubscribed) {
            MetaPageSubscribeLeadgenJob::dispatch($page->id);
            $subscribeJobDispatched = true;
        }

        if (! $page->status && $isSubscribed) {
            MetaPageUnsubscribeLeadgenJob::dispatch($page->id, $page->meta_page_id);
            $unsubscribeJobDispatched = true;
        }

        return [
            'is_subscribed' => $isSubscribed,
            'subscribe_job_dispatched' => $subscribeJobDispatched,
            'unsubscribe_job_dispatched' => $unsubscribeJobDispatched,
            'error' => $page->subscription_last_error,
        ];
    }

    public function subscribe(MetaPage $page): array
    {
        if (blank($page->page_access_token)) {
            $message = 'La pagina no tiene page_access_token para suscribir leadgen.';
            $this->markPageError($page, $message);
            throw new RuntimeException($message);
        }

        try {
            $response = $this->metaRequest('post', $page->meta_page_id.'/subscribed_apps', $page->page_access_token, [
                'subscribed_fields' => 'leadgen',
            ]);

            $payload = $this->decodeResponse($response);

            if (($payload['success'] ?? false) !== true) {
                throw new RuntimeException('Meta no confirmo la suscripcion leadgen: '.json_encode($payload, JSON_UNESCAPED_UNICODE));
            }

            $page->forceFill([
                'leadgen' => $payload,
                'is_leadgen_subscribed' => true,
                'subscription_checked_at' => now(),
                'subscription_updated_at' => now(),
                'subscription_last_error' => null,
            ])->saveQuietly();

            return $payload;
        } catch (\Throwable $exception) {
            $this->markPageError($page, $exception->getMessage());

            Log::error('Meta page leadgen subscription failed', [
                'meta_page_model_id' => $page->id,
                'meta_page_id' => $page->meta_page_id,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function unsubscribe(MetaPage $page): array
    {
        return $this->unsubscribeByMetaPageId($page->meta_page_id, $page);
    }

    public function unsubscribeByMetaPageId(string $metaPageId, ?MetaPage $page = null): array
    {
        $appAccessToken = $this->resolveAppAccessToken();

        try {
            $response = $this->metaRequest('delete', $metaPageId.'/subscribed_apps', $appAccessToken);
            $payload = $this->decodeResponse($response);

            if (($payload['success'] ?? false) !== true) {
                throw new RuntimeException('Meta no confirmo la cancelacion leadgen: '.json_encode($payload, JSON_UNESCAPED_UNICODE));
            }

            if ($page) {
                $page->forceFill([
                    'leadgen' => $payload,
                    'is_leadgen_subscribed' => false,
                    'subscription_checked_at' => now(),
                    'subscription_updated_at' => now(),
                    'subscription_last_error' => null,
                ])->saveQuietly();
            }

            return $payload;
        } catch (\Throwable $exception) {
            if ($page) {
                $this->markPageError($page, $exception->getMessage());
            }

            Log::error('Meta page leadgen unsubscribe failed', [
                'meta_page_model_id' => $page?->id,
                'meta_page_id' => $metaPageId,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function refreshSubscriptionStatus(MetaPage $page): bool
    {
        if (blank($page->page_access_token)) {
            $this->markPageError($page, 'La pagina no tiene page_access_token para validar leadgen.');
            return false;
        }

        try {
            $response = $this->metaRequest('get', $page->meta_page_id.'/subscribed_apps', $page->page_access_token, [
                'fields' => 'id,name,subscribed_fields',
            ]);

            $payload = $this->decodeResponse($response);
            $appId = $this->resolveMetaAppId();
            $isSubscribed = collect($payload['data'] ?? [])
                ->contains(function (array $item) use ($appId) {
                    return (string) ($item['id'] ?? '') === (string) $appId
                        && in_array('leadgen', $item['subscribed_fields'] ?? [], true);
                });

            $page->forceFill([
                'leadgen' => $payload,
                'is_leadgen_subscribed' => $isSubscribed,
                'subscription_checked_at' => now(),
                'subscription_last_error' => null,
            ])->saveQuietly();

            return $isSubscribed;
        } catch (\Throwable $exception) {
            $this->markPageError($page, $exception->getMessage());

            Log::error('Meta page leadgen subscription validation failed', [
                'meta_page_model_id' => $page->id,
                'meta_page_id' => $page->meta_page_id,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function markPageError(MetaPage $page, string $message): void
    {
        $page->forceFill([
            'subscription_checked_at' => now(),
            'subscription_last_error' => $message,
        ])->saveQuietly();
    }

    private function resolveMetaAppId(): string
    {
        $appId = MetaAccessToken::activeByType(MetaAccessToken::TYPE_USER_ACCESS_TOKEN)?->meta_app_id;

        if (blank($appId)) {
            throw new RuntimeException('No hay meta_app_id activo para validar leadgen.');
        }

        return (string) $appId;
    }

    private function resolveAppAccessToken(): string
    {
        $token = MetaAccessToken::activeByType(MetaAccessToken::TYPE_APP_ACCESS_TOKEN)?->working_token;

        if (blank($token)) {
            throw new RuntimeException('No hay APP_ACCESS_TOKEN activo en meta_access_tokens para cancelar leadgen.');
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
}
