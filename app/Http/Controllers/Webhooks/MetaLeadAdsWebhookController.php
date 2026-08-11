<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\SyncMetaLeadsJob;
use App\Jobs\SyncMetaPageLeadsJob;
use App\Services\Meta\MetaWebhookStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class MetaLeadAdsWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $verifyToken = config('services.meta.verify_token');
        $requestToken = $request->query('hub_verify_token', $request->query('hub.verify_token'));

        if (blank($verifyToken) || ! hash_equals((string) $verifyToken, (string) $requestToken)) {
            return response('', Response::HTTP_FORBIDDEN);
        }

        return response((string) $request->query('hub_challenge', $request->query('hub.challenge')), Response::HTTP_OK);
    }

    public function receive(Request $request, MetaWebhookStorageService $metaWebhookStorageService): JsonResponse
    {
        try {
            $metaWebhookStorageService->storeFromRequest($request);
        } catch (\Throwable $exception) {
            Log::error('Meta webhook payload could not be stored', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        if ($this->dispatchLeadgenPageSyncJobs($request) === 0) {
            SyncMetaLeadsJob::dispatch();
        }

        return response()->json(['received' => true], Response::HTTP_OK);
    }

    private function dispatchLeadgenPageSyncJobs(Request $request): int
    {
        $payload = $this->payloadFromRequest($request);
        $targets = [];

        foreach ($this->listFromValue(data_get($payload, 'entry')) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach ($this->listFromValue(data_get($entry, 'changes')) as $change) {
                if (! is_array($change) || $this->stringOrNull(data_get($change, 'field')) !== 'leadgen') {
                    continue;
                }

                $value = data_get($change, 'value');
                $pageId = $this->stringOrNull(data_get($value, 'page_id'))
                    ?: $this->stringOrNull(data_get($entry, 'id'));

                if (! $pageId) {
                    continue;
                }

                $targets[$pageId] = $this->resolveMetaEventTime($entry, $change, $value);
            }
        }

        foreach ($targets as $pageId => $metaEventTime) {
            SyncMetaPageLeadsJob::dispatch($pageId, $metaEventTime);
        }

        return count($targets);
    }

    private function payloadFromRequest(Request $request): array
    {
        $payload = $request->json()->all();

        if ($payload !== []) {
            return $payload;
        }

        $content = trim($request->getContent());

        if ($content === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function listFromValue(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return Arr::isAssoc($value) ? [$value] : $value;
    }

    private function resolveMetaEventTime(array $entry, array $change, mixed $value): ?string
    {
        foreach ([data_get($entry, 'time'), data_get($change, 'time'), data_get($value, 'created_time')] as $candidate) {
            $candidate = $this->stringOrNull($candidate);

            if ($candidate !== null) {
                return $candidate;
            }
        }

        return null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
