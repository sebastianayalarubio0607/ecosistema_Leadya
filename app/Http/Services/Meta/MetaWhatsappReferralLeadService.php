<?php

namespace App\Http\Services\Meta;

use App\Http\Services\Lead\LeadFunnelHistoryService;
use App\Http\Services\Lead\LeadService;
use App\Jobs\ProcessMetaWhatsappReferralLeadJob;
use App\Jobs\SendLeadToFacebook;
use App\Models\Lead;
use App\Models\MetaAdAccount;
use App\Models\MetaAdInsight;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MetaWhatsappReferralLeadService
{
    private const EFFECTIVE_LEAD = "whatsapp - campa\u{00F1}a";

    private const CAMPAIGN_ORIGIN = 'whatsapp';

    private const GAD_SOURCE = 'meta';

    public function __construct(
        private readonly LeadService $leadService,
        private readonly LeadFunnelHistoryService $leadFunnelHistoryService,
    ) {}

    public function processRequest(Request $request): int
    {
        return $this->dispatchRequest($request);
    }

    public function dispatchRequest(Request $request): int
    {
        $payload = $this->payloadFromRequest($request);
        $referralCount = $this->matchingReferralCount($payload);

        if ($referralCount > 0) {
            ProcessMetaWhatsappReferralLeadJob::dispatch($payload);
        }

        return $referralCount;
    }

    public function processPayload(array $payload): int
    {
        if ($this->stringOrNull(data_get($payload, 'object')) !== 'whatsapp_business_account') {
            return 0;
        }

        $created = 0;

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
                $contacts = $this->listFromValue(data_get($value, 'contacts'));
                $phoneNumberId = $this->stringOrNull(data_get($value, 'metadata.phone_number_id'));
                $displayPhoneNumber = $this->stringOrNull(data_get($value, 'metadata.display_phone_number'));

                foreach ($this->listFromValue(data_get($value, 'messages')) as $message) {
                    if (! is_array($message)) {
                        continue;
                    }

                    $referral = data_get($message, 'referral');

                    if (! is_array($referral)) {
                        continue;
                    }

                    try {
                        if ($this->createLeadFromMessage($payload, $wabaId, $phoneNumberId, $displayPhoneNumber, $contacts, $message, $referral)) {
                            $created++;
                        }
                    } catch (\Throwable $exception) {
                        Log::error('Meta WhatsApp referral lead could not be created', [
                            'exception' => $exception::class,
                            'message' => $exception->getMessage(),
                            'message_id' => $this->stringOrNull(data_get($message, 'id')),
                            'source_id' => $this->stringOrNull(data_get($referral, 'source_id')),
                        ]);
                    }
                }
            }
        }

        return $created;
    }

    private function matchingReferralCount(array $payload): int
    {
        if ($this->stringOrNull(data_get($payload, 'object')) !== 'whatsapp_business_account') {
            return 0;
        }

        $count = 0;

        foreach ($this->listFromValue(data_get($payload, 'entry')) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach ($this->listFromValue(data_get($entry, 'changes')) as $change) {
                if (! is_array($change) || $this->stringOrNull(data_get($change, 'field')) !== 'messages') {
                    continue;
                }

                foreach ($this->listFromValue(data_get($change, 'value.messages')) as $message) {
                    if (is_array($message) && is_array(data_get($message, 'referral'))) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    private function createLeadFromMessage(
        array $payload,
        ?string $wabaId,
        ?string $phoneNumberId,
        ?string $displayPhoneNumber,
        array $contacts,
        array $message,
        array $referral,
    ): bool {
        $sourceId = $this->stringOrNull(data_get($referral, 'source_id'));
        $customerId = $this->resolveCustomerId($sourceId, $wabaId, $phoneNumberId, $displayPhoneNumber);

        if (! $customerId) {
            Log::warning('Meta WhatsApp referral lead skipped because no customer was resolved.', [
                'source_id' => $sourceId,
                'waba_id' => $wabaId,
                'phone_number_id' => $phoneNumberId,
                'display_phone_number' => $displayPhoneNumber,
                'message_id' => $this->stringOrNull(data_get($message, 'id')),
            ]);

            return false;
        }

        $messageFrom = $this->firstString(
            data_get($message, 'from'),
            data_get($message, 'from_user_id')
        );
        $contact = $this->resolveContact($contacts, $messageFrom);
        $leadPayload = $this->buildLeadPayload($customerId, $wabaId, $displayPhoneNumber, $contact, $message, $referral);

        if ($this->findExistingLead($leadPayload)) {
            return false;
        }

        $lead = $this->leadService->createLead($leadPayload);
        $lead->loadMissing('crmState.metaEvent', 'crmState.whatsappEvent');

        $this->dispatchMetaConversion($lead);
        $this->leadFunnelHistoryService->recordInitialLead($lead);

        Log::info('Meta WhatsApp referral lead created from webhook.', [
            'lead_id' => $lead->id,
            'customer_id' => $lead->customer_id,
            'source_id' => $sourceId,
            'ctwa_clid' => $lead->ctwa_clid,
        ]);

        return true;
    }

    private function buildLeadPayload(
        int $customerId,
        ?string $wabaId,
        ?string $displayPhoneNumber,
        ?array $contact,
        array $message,
        array $referral,
    ): array {
        $messageBody = $this->stringOrNull(data_get($message, 'text.body'));
        $name = $this->firstString(
            data_get($contact, 'name'),
            data_get($contact, 'profile.name'),
            $this->extractLineValue($messageBody, ['Full name'])
        );
        $phone = $this->firstString(
            data_get($contact, 'wa_id'),
            data_get($contact, 'user_id'),
            data_get($message, 'from'),
            data_get($message, 'from_user_id'),
            $this->extractPhoneFromText($messageBody)
        );

        return [
            'customer_id' => $customerId,
            'integration_id' => null,
            'name' => $name,
            'last_name' => 'Whastaapp',
            'phone' => $phone,
            'tc' => true,
            'effective_lead' => self::EFFECTIVE_LEAD,
            'page_url' => $this->firstString(data_get($referral, 'video_url'), data_get($referral, 'source_url')),
            'campaign_origin' => self::CAMPAIGN_ORIGIN,
            'message' => $messageBody,
            'plataforma' => $this->stringOrNull(data_get($referral, 'media_type')),
            'meta_id_ad' => $this->stringOrNull(data_get($referral, 'source_id')),
            'gad_source' => self::GAD_SOURCE,
            'meta_payload' => $referral,
            'whasapp_user_id' => $this->firstString(
                data_get($contact, 'wa_id'),
                data_get($contact, 'user_id'),
                data_get($message, 'from'),
                data_get($message, 'from_user_id')
            ),
            'ctwa_clid' => $this->stringOrNull(data_get($referral, 'ctwa_clid')),
            'whatsapp_business_account_id' => $wabaId,
            'number_whatsApp_companies' => $displayPhoneNumber,
            'WhatsApp_username' => $this->stringOrNull(data_get($contact, 'profile.username')),
        ];
    }

    private function dispatchMetaConversion(Lead $lead): void
    {
        if (blank($lead->crm_state)) {
            SendLeadToFacebook::dispatch($lead->id, (int) $lead->customer_id, 'Lead');

            return;
        }

        if (! empty($lead->crmState?->whatsapp_event_id) || ! empty($lead->crmState?->meta_event_id)) {
            SendLeadToFacebook::dispatch($lead->id, (int) $lead->customer_id);
        }
    }

    private function findExistingLead(array $payload): ?Lead
    {
        $customerId = $payload['customer_id'] ?? null;
        $ctwaClid = $this->stringOrNull($payload['ctwa_clid'] ?? null);

        if ($customerId && $ctwaClid) {
            return Lead::query()
                ->where('customer_id', $customerId)
                ->where('ctwa_clid', $ctwaClid)
                ->first();
        }

        $whatsappUserId = $this->stringOrNull($payload['whasapp_user_id'] ?? null);
        $metaAdId = $this->stringOrNull($payload['meta_id_ad'] ?? null);
        $wabaId = $this->stringOrNull($payload['whatsapp_business_account_id'] ?? null);

        if (! $customerId || ! $whatsappUserId || ! $metaAdId || ! $wabaId) {
            return null;
        }

        return Lead::query()
            ->where('customer_id', $customerId)
            ->where('whasapp_user_id', $whatsappUserId)
            ->where('meta_id_ad', $metaAdId)
            ->where('whatsapp_business_account_id', $wabaId)
            ->first();
    }

    private function resolveCustomerIdFromSourceId(?string $sourceId): ?int
    {
        if (! $sourceId) {
            return null;
        }

        $accountId = MetaAdInsight::query()
            ->where('ad_id', $sourceId)
            ->whereNotNull('account_id')
            ->orderByDesc('date_stop')
            ->orderByDesc('id')
            ->value('account_id');

        if (! $accountId) {
            return $this->resolveCustomerIdFromExistingLead($sourceId);
        }

        $account = MetaAdAccount::query()
            ->whereIn('meta_account_id', $this->accountIdCandidates((string) $accountId))
            ->whereNotNull('customer_id')
            ->first(['id', 'customer_id']);

        return $account?->customer_id
            ? (int) $account->customer_id
            : $this->resolveCustomerIdFromExistingLead($sourceId);
    }

    private function resolveCustomerId(
        ?string $sourceId,
        ?string $wabaId,
        ?string $phoneNumberId,
        ?string $displayPhoneNumber,
    ): ?int {
        if ($sourceId) {
            $customerId = $this->resolveCustomerIdFromSourceId($sourceId);

            if ($customerId) {
                return $customerId;
            }
        }

        return $this->resolveCustomerIdFromWhatsapp($wabaId, $phoneNumberId, $displayPhoneNumber);
    }

    private function resolveCustomerIdFromExistingLead(string $sourceId): ?int
    {
        $customerId = Lead::query()
            ->where('meta_id_ad', $sourceId)
            ->whereNotNull('customer_id')
            ->latest('id')
            ->value('customer_id');

        return $customerId ? (int) $customerId : null;
    }

    private function resolveCustomerIdFromWhatsapp(
        ?string $wabaId,
        ?string $phoneNumberId,
        ?string $displayPhoneNumber,
    ): ?int {
        if (! $wabaId && ! $phoneNumberId && ! $displayPhoneNumber) {
            return null;
        }

        $customerId = $this->resolveCustomerIdFromMetaWhatsapp($wabaId, $phoneNumberId);

        if ($customerId) {
            return $customerId;
        }

        $query = Lead::query()->whereNotNull('customer_id');

        $query->where(function ($innerQuery) use ($wabaId, $displayPhoneNumber): void {
            if ($wabaId) {
                $innerQuery->orWhere('whatsapp_business_account_id', $wabaId);
            }

            if ($displayPhoneNumber) {
                $innerQuery->orWhere('number_whatsApp_companies', $displayPhoneNumber);
            }
        });

        $customerId = $query->latest('id')->value('customer_id');

        return $customerId ? (int) $customerId : null;
    }

    private function resolveCustomerIdFromMetaWhatsapp(?string $wabaId, ?string $phoneNumberId): ?int
    {
        if (! $wabaId && ! $phoneNumberId) {
            return null;
        }

        if (! Schema::hasTable('meta_whatsapps') || ! Schema::hasTable('customer_meta_whatsapp')) {
            return null;
        }

        $query = DB::table('meta_whatsapps')
            ->join('customer_meta_whatsapp', 'customer_meta_whatsapp.meta_whatsapp_id', '=', 'meta_whatsapps.id')
            ->whereNotNull('customer_meta_whatsapp.customer_id');

        $query->where(function ($innerQuery) use ($wabaId, $phoneNumberId): void {
            if ($wabaId) {
                $innerQuery->orWhere('meta_whatsapps.waba_id', $wabaId);
            }

            if ($phoneNumberId) {
                $innerQuery->orWhere('meta_whatsapps.phone_number_id', $phoneNumberId);
            }
        });

        $customerId = $query
            ->orderByDesc('customer_meta_whatsapp.id')
            ->value('customer_meta_whatsapp.customer_id');

        return $customerId ? (int) $customerId : null;
    }

    private function resolveContact(array $contacts, ?string $messageFrom): ?array
    {
        foreach ($contacts as $contact) {
            if (! is_array($contact)) {
                continue;
            }

            if ($messageFrom && in_array($messageFrom, array_filter([
                $this->stringOrNull(data_get($contact, 'wa_id')),
                $this->stringOrNull(data_get($contact, 'user_id')),
            ]), true)) {
                return $contact;
            }
        }

        foreach ($contacts as $contact) {
            if (is_array($contact)) {
                return $contact;
            }
        }

        return null;
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

    private function firstString(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            $value = $this->stringOrNull($value);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function extractLineValue(?string $body, array $labels): ?string
    {
        if ($body === null) {
            return null;
        }

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$label, $value] = array_map('trim', explode(':', $line, 2));

            foreach ($labels as $expectedLabel) {
                if (strcasecmp($label, $expectedLabel) === 0 && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function extractPhoneFromText(?string $body): ?string
    {
        $fromLabel = $this->extractLineValue($body, ['Phone number', 'Phone']);

        if ($fromLabel !== null) {
            return $fromLabel;
        }

        if ($body !== null && preg_match('/\+?\d[\d\s().-]{7,}\d/', $body, $matches) === 1) {
            return trim($matches[0]);
        }

        return null;
    }

    private function accountIdCandidates(string $value): array
    {
        $value = trim($value);
        $withoutPrefix = Str::startsWith($value, 'act_') ? Str::after($value, 'act_') : $value;

        return array_values(array_unique(array_filter([
            $value,
            $withoutPrefix,
            'act_'.$withoutPrefix,
        ])));
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
