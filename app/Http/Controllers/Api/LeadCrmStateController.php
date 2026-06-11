<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Services\Lead\LeadAdSourceClassifier;
use App\Http\Services\Lead\LeadFunnelHistoryService;
use App\Jobs\SendLeadToFacebook;
use App\Jobs\SendLeadToGoogleAds;
use App\Models\CrmState;
use App\Models\Integration;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LeadCrmStateController extends Controller
{
    public function update(Request $request, string $public_key, LeadFunnelHistoryService $historyService)
    {
        $integration = Integration::query()
            ->where('public_key', $public_key)
            ->first();

        if (! $integration) {
            return response()->json(['message' => 'Integration not found'], 404);
        }

        $data = $this->normalizeWebhookPayload($request);
        $statuses = data_get($data, 'leads.status', []);
        $updates = data_get($data, 'leads.update', []);
        $isKommoStatusPayload = is_array($statuses) && count($statuses) > 0;
        $isKommoUpdatePayload = is_array($updates) && count($updates) > 0;
        $isFreshworksPayload = $this->isFreshworksPayload($data);

        if (! $isKommoStatusPayload && ! $isKommoUpdatePayload && ! $isFreshworksPayload) {
            Log::warning('Webhook sin payload soportado para actualizar crm_state', [
                'public_key' => $public_key,
                'content_type' => $request->header('content-type'),
                'data_keys' => array_keys($data),
            ]);

            return response()->json([
                'message' => 'Invalid payload: leads.status, leads.update or Freshworks fields not found',
            ], 422);
        }

        $updated = 0;
        $valueUpdated = 0;
        $notFound = [];
        $crmIdPrefix = $integration->crmIdPrefix();

        if ($isKommoUpdatePayload) {
            $this->processKommoLeadUpdates(
                $updates,
                $crmIdPrefix,
                $historyService,
                $updated,
                $valueUpdated,
                $notFound
            );
        }

        if ($isKommoStatusPayload) {
            foreach ($statuses as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $kommoLeadId = (string) ($item['id'] ?? '');
                $statusId = (string) ($item['status_id'] ?? '');

                if ($kommoLeadId === '' || $statusId === '') {
                    continue;
                }

                $crmIdToFind = $crmIdPrefix . '-' . $kommoLeadId;
                $newCrmState = $crmIdPrefix . '-' . $statusId;

                $this->processLeadStateChange(
                    $crmIdToFind,
                    $newCrmState,
                    $historyService,
                    $updated,
                    $notFound
                );
            }
        }

        if ($isFreshworksPayload) {
            $contactId = (string) data_get($data, 'contact_id');
            $statusName = trim((string) data_get($data, 'contact_contact_status_name'));
            $crmIdToFind = $crmIdPrefix . '-' . $contactId;
            $newCrmState = $this->resolveFreshworksCrmStateId($crmIdPrefix, $statusName);

            if ($newCrmState === null) {
                Log::warning('Freshworks webhook con estado no resoluble', [
                    'integration_id' => $integration->id,
                    'public_key' => $public_key,
                    'crm_id' => $crmIdToFind,
                    'freshworks_status_name' => $statusName,
                ]);
            } else {
                $this->processLeadStateChange(
                    $crmIdToFind,
                    $newCrmState,
                    $historyService,
                    $updated,
                    $notFound
                );
            }
        }

        return response()->json([
            'message' => 'OK',
            'integration_id' => $integration->id,
            'updated' => $updated,
            'value_updated' => $valueUpdated,
            'not_found' => array_values(array_unique($notFound)),
        ]);
    }

    private function normalizeWebhookPayload(Request $request): array
    {
        $data = $request->all();

        foreach (['account', 'leads'] as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $decoded = json_decode($data[$key], true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $data[$key] = $decoded;
                }
            }
        }

        return $data;
    }

    private function processKommoLeadUpdates(
        array $updates,
        string $crmIdPrefix,
        LeadFunnelHistoryService $historyService,
        int &$updated,
        int &$valueUpdated,
        array &$notFound
    ): void {
        foreach ($updates as $item) {
            if (! is_array($item)) {
                continue;
            }

            $kommoLeadId = (string) ($item['id'] ?? '');

            if ($kommoLeadId === '') {
                continue;
            }

            $crmIdToFind = $crmIdPrefix . '-' . $kommoLeadId;
            $leads = Lead::query()
                ->where('crm_id', $crmIdToFind)
                ->get();

            if ($leads->isEmpty()) {
                $notFound[] = $crmIdToFind;
                continue;
            }

            $incomingValue = $this->extractLeadValue($item);
            $statusId = (string) ($item['status_id'] ?? '');
            $newCrmState = $statusId !== '' ? $crmIdPrefix . '-' . $statusId : null;

            foreach ($leads as $lead) {
                if ($this->updateLeadValueIfChanged($lead, $incomingValue)) {
                    $valueUpdated++;
                }

                if ($newCrmState !== null) {
                    $this->processLeadStateChangeForLead(
                        $lead,
                        $newCrmState,
                        $historyService,
                        $updated
                    );
                }
            }
        }
    }

    private function extractLeadValue(array $item): mixed
    {
        $acceptedFields = [
            'price',
            'value',
            'presupuesto',
            'valor',
            'valor servicio',
            'valor de servicio',
            'valor producto',
            'valor de producto',
            'valor o producto',
        ];

        foreach ($item as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (in_array($this->normalizeValueFieldName($key), $acceptedFields, true)) {
                return $value;
            }
        }

        foreach ((array) ($item['custom_fields_values'] ?? []) as $customField) {
            if (! is_array($customField)) {
                continue;
            }

            $fieldName = (string) ($customField['field_name'] ?? $customField['name'] ?? '');

            if (! in_array($this->normalizeValueFieldName($fieldName), $acceptedFields, true)) {
                continue;
            }

            $values = $customField['values'] ?? [];
            $firstValue = is_array($values) ? reset($values) : null;

            if (is_array($firstValue) && array_key_exists('value', $firstValue)) {
                return $firstValue['value'];
            }
        }

        return null;
    }

    private function normalizeValueFieldName(string $field): string
    {
        $field = mb_strtolower(trim($field));
        $field = str_replace(['_', '-'], ' ', $field);
        $field = preg_replace('/\s+/', ' ', $field);

        return trim((string) $field);
    }

    private function updateLeadValueIfChanged(Lead $lead, mixed $incomingValue): bool
    {
        $normalizedIncomingValue = $this->normalizeMoneyValue($incomingValue);

        if ($normalizedIncomingValue === null) {
            return false;
        }

        if ($this->normalizeMoneyValue($lead->value) === $normalizedIncomingValue) {
            return false;
        }

        $lead->value = $normalizedIncomingValue;
        $lead->save();

        return true;
    }

    private function normalizeMoneyValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);
        $value = str_ireplace(['$', 'cop', 'usd', ' '], '', $value);

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $lastComma = strrpos($value, ',');
            $lastDot = strrpos($value, '.');

            $value = $lastComma > $lastDot
                ? str_replace('.', '', $value)
                : str_replace(',', '', $value);
        }

        $value = str_replace(',', '.', $value);

        if (! is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function isFreshworksPayload(array $data): bool
    {
        $contactId = data_get($data, 'contact_id');
        $statusName = data_get($data, 'contact_contact_status_name');

        return $contactId !== null
            && $contactId !== ''
            && is_string($statusName)
            && trim($statusName) !== '';
    }

    private function resolveFreshworksCrmStateId(int|string $integrationId, string $statusName): ?string
    {
        if ($statusName === '') {
            return null;
        }

        return CrmState::query()
            ->where('id', 'like', $integrationId . '-%')
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($statusName))])
            ->value('id');
    }

    private function processLeadStateChange(
        string $crmIdToFind,
        string $newCrmState,
        LeadFunnelHistoryService $historyService,
        int &$updated,
        array &$notFound
    ): void {
        $leads = Lead::query()
            ->where('crm_id', $crmIdToFind)
            ->get();

        if ($leads->isEmpty()) {
            $notFound[] = $crmIdToFind;
            return;
        }

        foreach ($leads as $lead) {
            $this->processLeadStateChangeForLead(
                $lead,
                $newCrmState,
                $historyService,
                $updated
            );
        }
    }

    private function processLeadStateChangeForLead(
        Lead $lead,
        string $newCrmState,
        LeadFunnelHistoryService $historyService,
        int &$updated
    ): void {
        if ((string) $lead->crm_state === (string) $newCrmState) {
            return;
        }

        $lead->crm_state = $newCrmState;
        $lead->save();

        $historyService->recordIfFunnelChanged($lead);
        $updated++;

        $this->dispatchTrackingJobsForLead($lead, $newCrmState);
    }

    private function dispatchTrackingJobsForLead(Lead $lead, string $newCrmState): void
    {
        $adSource = LeadAdSourceClassifier::classify($lead);

        if ($adSource['is_meta_ads']) {
            if (empty($lead->crm_state)) {
                return;
            }

            $lead->unsetRelation('crmState');
            $lead->load('crmState');

            if (empty($lead->crmState?->meta_event_id)) {
                return;
            }

            try {
                SendLeadToFacebook::dispatch($lead->id, $lead->customer_id);
            } catch (\Throwable $exception) {
                Log::warning('No fue posible despachar SendLeadToFacebook desde cambio de crm_state', [
                    'lead_id' => $lead->id,
                    'campaign_origin' => $lead->campaign_origin,
                    'source_name' => $adSource['source_name'],
                    'crm_state' => $newCrmState,
                    'message' => $exception->getMessage(),
                ]);
            }
        } elseif ($adSource['is_google_ads']) {
            try {
                SendLeadToGoogleAds::dispatch($lead->id, $newCrmState);
            } catch (\Throwable $exception) {
                Log::warning('No fue posible despachar SendLeadToGoogleAds desde cambio de crm_state', [
                    'lead_id' => $lead->id,
                    'campaign_origin' => $lead->campaign_origin,
                    'source_name' => $adSource['source_name'],
                    'crm_state' => $newCrmState,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

}
