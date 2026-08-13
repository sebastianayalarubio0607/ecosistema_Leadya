<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\MetaWhatsappMessage;
use App\Services\Meta\MetaWebhookStorageService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class MetaWhatsAppWebhookController extends Controller
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
            Log::error('Meta WhatsApp webhook payload could not be stored in generic events', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        try {
            $stored = $this->storeAdReferralMessages($request);
        } catch (\Throwable $exception) {
            Log::error('Meta WhatsApp webhook payload could not be processed', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            $stored = 0;
        }

        return response()->json([
            'received' => true,
            'stored_messages' => $stored,
        ], Response::HTTP_OK);
    }

    private function storeAdReferralMessages(Request $request): int
    {
        $payload = $this->payloadFromRequest($request);
        $stored = 0;

        foreach ($this->listFromValue(data_get($payload, 'entry')) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $wabaId = $this->stringOrNull(data_get($entry, 'id'));

            foreach ($this->listFromValue(data_get($entry, 'changes')) as $change) {
                if (! is_array($change) || $this->stringOrNull(data_get($change, 'field')) !== 'messages') {
                    continue;
                }

                $value = data_get($change, 'value');
                $phoneNumberId = $this->stringOrNull(data_get($value, 'metadata.phone_number_id'));
                $contactsByWaId = $this->contactsByWaId($this->listFromValue(data_get($value, 'contacts')));

                foreach ($this->listFromValue(data_get($value, 'messages')) as $message) {
                    if (! is_array($message)) {
                        continue;
                    }

                    $referral = data_get($message, 'referral');

                    if (! is_array($referral) || $this->stringOrNull($referral['source_type'] ?? null) !== 'ad') {
                        continue;
                    }

                    $messageId = $this->stringOrNull(data_get($message, 'id'));

                    if ($messageId === null) {
                        continue;
                    }

                    $waId = $this->stringOrNull(data_get($message, 'from'));

                    if ($waId === null && $contactsByWaId !== []) {
                        $waId = array_key_first($contactsByWaId);
                    }

                    $stored += (int) $this->storeMessage($payload, $wabaId, $phoneNumberId, $waId, $messageId, $message, $referral);
                }
            }
        }

        return $stored;
    }

    private function storeMessage(
        array $payload,
        ?string $wabaId,
        ?string $phoneNumberId,
        ?string $waId,
        string $messageId,
        array $message,
        array $referral,
    ): bool {
        $isFirstMessage = $waId !== null
            && ! MetaWhatsappMessage::query()->where('wa_id', $waId)->exists();

        try {
            return MetaWhatsappMessage::query()->create([
                'waba_id' => $wabaId,
                'phone_number_id' => $phoneNumberId,
                'wa_id' => $waId,
                'message_id' => $messageId,
                'message_timestamp' => $this->parseMetaTimestamp(data_get($message, 'timestamp')),
                'ctwa_clid' => $this->stringOrNull(data_get($referral, 'ctwa_clid')),
                'source_id' => $this->stringOrNull(data_get($referral, 'source_id')),
                'source_url' => $this->stringOrNull(data_get($referral, 'source_url')),
                'headline' => $this->stringOrNull(data_get($referral, 'headline')),
                'body' => $this->stringOrNull(data_get($referral, 'body')),
                'source_type' => $this->stringOrNull(data_get($referral, 'source_type')),
                'is_first_message' => $isFirstMessage,
                'referral' => $referral,
                'message' => $message,
                'payload' => $payload,
            ])->wasRecentlyCreated;
        } catch (QueryException $exception) {
            if ($this->isDuplicateEntry($exception)) {
                return false;
            }

            throw $exception;
        }
    }

    private function contactsByWaId(array $contacts): array
    {
        $indexed = [];

        foreach ($contacts as $contact) {
            if (! is_array($contact)) {
                continue;
            }

            $waId = $this->stringOrNull(data_get($contact, 'wa_id'));

            if ($waId !== null) {
                $indexed[$waId] = $contact;
            }
        }

        return $indexed;
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

    private function parseMetaTimestamp(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return is_numeric($value) ? Carbon::createFromTimestamp((int) $value) : Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
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
