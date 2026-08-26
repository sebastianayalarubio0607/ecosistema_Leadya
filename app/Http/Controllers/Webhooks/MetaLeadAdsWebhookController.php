<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Services\Meta\MetaAssetStatusSyncService;
use App\Http\Services\Meta\MetaWhatsappReferralLeadService;
use App\Jobs\SyncMetaAssetStatusesForCustomerJob;
use App\Jobs\SyncMetaLeadsJob;
use App\Jobs\SyncMetaPageLeadsJob;
use App\Services\Meta\MetaWebhookStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
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

    public function receive(
        Request $request,
        MetaWebhookStorageService $metaWebhookStorageService,
        MetaAssetStatusSyncService $metaAssetStatusSyncService,
        MetaWhatsappReferralLeadService $metaWhatsappReferralLeadService,
    ): JsonResponse {
        $storedEvents = new Collection;

        try {
            $result = $metaWebhookStorageService->storeFromRequest($request);
            $storedEvents = $result instanceof Collection ? $result : new Collection;
        } catch (\Throwable $exception) {
            Log::error('Meta webhook payload could not be stored', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        $whatsappReferralJobs = 0;

        try {
            $whatsappReferralJobs = $metaWhatsappReferralLeadService->dispatchRequest($request);
        } catch (\Throwable $exception) {
            Log::error('Meta WhatsApp referral lead webhook payload could not be processed', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        $hasAssetStatusEvent = $this->hasMetaAssetStatusEvent($storedEvents);
        $this->dispatchMetaAssetStatusJobs($storedEvents, $metaAssetStatusSyncService);

        if (
            $this->dispatchLeadgenPageSyncJobs($request) === 0
            && ! $hasAssetStatusEvent
            && $whatsappReferralJobs === 0
            && $this->shouldDispatchGlobalLeadSyncFallback($request)
        ) {
            SyncMetaLeadsJob::dispatch();
        }

        return response()->json(['received' => true], Response::HTTP_OK);
    }

    private function dispatchMetaAssetStatusJobs(Collection $events, MetaAssetStatusSyncService $service): int
    {
        $jobsDispatched = 0;
        $customerEvents = [];

        foreach ($events as $event) {
            if (! $event || ! $this->isMetaAssetStatusEvent($event->object, $event->field)) {
                continue;
            }

            $customerId = $service->resolveCustomerIdFromWebhookEvent($event);

            if (! $customerId) {
                Log::warning('Meta ad account asset status webhook skipped because no customer was resolved.', [
                    'meta_webhook_event_id' => $event->id,
                    'account_id' => $event->account_id,
                    'field' => $event->field,
                ]);

                continue;
            }

            $customerEvents[$customerId] ??= $event->id;
        }

        foreach ($customerEvents as $customerId => $eventId) {
            SyncMetaAssetStatusesForCustomerJob::dispatch((int) $customerId, (int) $eventId, 'webhook');
            $jobsDispatched++;
        }

        return $jobsDispatched;
    }

    private function isMetaAssetStatusEvent(?string $object, ?string $field): bool
    {
        return $object === 'ad_account'
            && in_array($field, ['with_issues_ad_objects', 'in_process_ad_objects'], true);
    }

    private function hasMetaAssetStatusEvent(Collection $events): bool
    {
        return $events->contains(fn ($event) => $event && $this->isMetaAssetStatusEvent($event->object, $event->field));
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

    private function shouldDispatchGlobalLeadSyncFallback(Request $request): bool
    {
        $payload = $this->payloadFromRequest($request);
        $object = $this->stringOrNull(data_get($payload, 'object'));

        if ($object === 'whatsapp_business_account' || $object === 'ad_account') {
            return false;
        }

        return $object === null || $object === 'page';
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
