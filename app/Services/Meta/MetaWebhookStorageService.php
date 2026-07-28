<?php

namespace App\Services\Meta;

use App\Models\MetaWebhookEvent;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class MetaWebhookStorageService
{
    private const SAFE_HEADERS = [
        'content-type',
        'user-agent',
        'x-hub-signature',
        'x-hub-signature-256',
        'x-forwarded-for',
        'x-request-id',
    ];

    public function storeFromRequest(Request $request): void
    {
        $payload = $this->payloadFromRequest($request);
        $object = $this->stringOrNull(data_get($payload, 'object'));
        $entries = $this->listFromValue(data_get($payload, 'entry'));
        $receivedAt = now();
        $headers = $this->safeHeaders($request);

        if ($entries === []) {
            $this->storeEvent(
                request: $request,
                payload: $payload,
                value: null,
                object: $object,
                entry: null,
                change: null,
                receivedAt: $receivedAt,
                headers: $headers,
            );

            return;
        }

        foreach ($entries as $entry) {
            $entryPayload = is_array($entry) ? $entry : ['value' => $entry];
            $changes = $this->listFromValue(data_get($entryPayload, 'changes'));

            if ($changes === []) {
                $this->storeEvent(
                    request: $request,
                    payload: $payload,
                    value: null,
                    object: $object,
                    entry: $entryPayload,
                    change: null,
                    receivedAt: $receivedAt,
                    headers: $headers,
                );

                continue;
            }

            foreach ($changes as $change) {
                $changePayload = is_array($change) ? $change : ['value' => $change];

                $this->storeEvent(
                    request: $request,
                    payload: $payload,
                    value: data_get($changePayload, 'value'),
                    object: $object,
                    entry: $entryPayload,
                    change: $changePayload,
                    receivedAt: $receivedAt,
                    headers: $headers,
                );
            }
        }
    }

    private function storeEvent(
        Request $request,
        array $payload,
        mixed $value,
        ?string $object,
        ?array $entry,
        ?array $change,
        Carbon $receivedAt,
        array $headers,
    ): void {
        $field = $this->stringOrNull(data_get($change, 'field'));
        $entryId = $this->stringOrNull(data_get($entry, 'id'));
        $metaEventTime = $this->resolveMetaEventTime($entry, $change, $value);

        $attributes = array_merge([
            'event_hash' => $this->eventHash($object, $entryId, $field, $value, $metaEventTime),
            'product' => $this->detectProduct($payload, $object, $field, $value),
            'object' => $object,
            'field' => $field,
            'entry_id' => $entryId,
            'meta_event_time' => $metaEventTime,
            'received_at' => $receivedAt,
            'processing_status' => 'received',
            'processing_error' => null,
            'value' => $this->jsonValueOrNull($value),
            'payload' => $payload,
            'request_headers' => $headers,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ], $this->knownIdentifiers($payload, $entry, $change, $value));

        try {
            if (MetaWebhookEvent::query()->where('event_hash', $attributes['event_hash'])->exists()) {
                return;
            }

            MetaWebhookEvent::query()->create($attributes);
        } catch (QueryException $exception) {
            if ($this->isDuplicateEntry($exception)) {
                return;
            }

            throw $exception;
        }
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

    private function detectProduct(array $payload, ?string $object, ?string $field, mixed $value): string
    {
        $explicitProduct = $this->stringOrNull(data_get($payload, 'product'));

        if ($explicitProduct !== null) {
            return $explicitProduct;
        }

        if ($object !== null) {
            return $object;
        }

        if ($field !== null) {
            return $field;
        }

        if (is_array($value)) {
            if (data_get($value, 'leadgen_id') !== null) {
                return 'leadgen';
            }

            if (data_get($value, 'sender.id') !== null || data_get($value, 'recipient.id') !== null) {
                return 'messages';
            }

            if (data_get($value, 'account_id') !== null || data_get($value, 'ad_account_id') !== null) {
                return 'account';
            }
        }

        return 'unknown';
    }

    private function knownIdentifiers(array $payload, ?array $entry, ?array $change, mixed $value): array
    {
        $sources = [
            is_array($value) ? $value : [],
            $change ?? [],
            $entry ?? [],
            $payload,
        ];

        return [
            'app_id' => $this->firstString($sources, ['app_id']),
            'page_id' => $this->firstString($sources, ['page_id']),
            'account_id' => $this->firstString($sources, ['account_id', 'ad_account_id']),
            'leadgen_id' => $this->firstString($sources, ['leadgen_id']),
            'form_id' => $this->firstString($sources, ['form_id']),
            'ad_id' => $this->firstString($sources, ['ad_id']),
            'adset_id' => $this->firstString($sources, ['adset_id', 'ad_set_id']),
            'campaign_id' => $this->firstString($sources, ['campaign_id']),
            'sender_id' => $this->firstString($sources, ['sender.id', 'sender_id']),
            'recipient_id' => $this->firstString($sources, ['recipient.id', 'recipient_id']),
        ];
    }

    private function firstString(array $sources, array $keys): ?string
    {
        foreach ($sources as $source) {
            foreach ($keys as $key) {
                $value = $this->stringOrNull(data_get($source, $key));

                if ($value !== null) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function resolveMetaEventTime(?array $entry, ?array $change, mixed $value): ?Carbon
    {
        $candidates = [
            data_get($entry, 'time'),
            data_get($change, 'time'),
            is_array($value) ? data_get($value, 'created_time') : null,
            is_array($value) ? data_get($value, 'timestamp') : null,
        ];

        foreach ($candidates as $candidate) {
            $parsed = $this->parseMetaTime($candidate);

            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }

    private function parseMetaTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Carbon::createFromTimestamp((int) $value);
            }

            if (is_string($value)) {
                return Carbon::parse($value);
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function eventHash(
        ?string $object,
        ?string $entryId,
        ?string $field,
        mixed $value,
        ?Carbon $metaEventTime,
    ): string {
        return hash('sha256', $this->stableJson([
            'object' => $object,
            'entry_id' => $entryId,
            'field' => $field,
            'value' => $value,
            'meta_event_time' => $metaEventTime?->toISOString(),
        ]));
    }

    private function stableJson(mixed $value): string
    {
        return json_encode($this->sortRecursive($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function sortRecursive(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->sortRecursive($item);
        }

        if (Arr::isAssoc($value)) {
            ksort($value);
        }

        return $value;
    }

    private function safeHeaders(Request $request): array
    {
        $headers = [];

        foreach (self::SAFE_HEADERS as $header) {
            if ($request->headers->has($header)) {
                $headers[$header] = $request->headers->all($header);
            }
        }

        return $headers;
    }

    private function jsonValueOrNull(mixed $value): mixed
    {
        return $value === null ? null : $value;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function isDuplicateEntry(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return $sqlState === '23000' || in_array($driverCode, [1062, 19], true);
    }
}
