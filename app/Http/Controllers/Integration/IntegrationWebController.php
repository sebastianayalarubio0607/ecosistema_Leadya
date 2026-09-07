<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Http\Services\Integration\HubspotPipelineSyncService;
use App\Http\Services\Integration\KommoPipelineSyncService;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\Integrationtype;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IntegrationWebController extends Controller
{
    private const DEFAULT_GOHIGHLEVEL_URL = 'https://services.leadconnectorhq.com/contacts/upsert';
    private const VARIABLE_MAPPING_INTEGRATION_TYPES = ['atom', 'zoho', 'salesforce', 'monday', 'lety', 'hubspot', 'gohighlevel', 'gohighlevel_oportunidad', 'zapnito_invitacion'];

    public function index(Request $request)
    {
        $q = $request->get('q');
        $name = $request->get('name');
        $customerId = $request->get('customer_id');
        $typeId = $request->get('integrationtype_id');
        $priority = $request->get('priority');
        $hasPriorityColumn = $this->hasIntegrationPriorityColumn();
        $hasDestinationUrlColumn = $this->hasIntegrationDestinationUrlColumn();

        $integrations = Integration::query()
            ->with(['customer:id,name', 'integrationtype:id,name'])
            ->when($q, function ($query) use ($q, $hasDestinationUrlColumn) {
                $query->where(function ($query) use ($q, $hasDestinationUrlColumn) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%")
                        ->orWhere('url', 'like', "%{$q}%")
                        ->orWhere('public_key', 'like', "%{$q}%");

                    if ($hasDestinationUrlColumn) {
                        $query->orWhere('urldestino', 'like', "%{$q}%");
                    }
                });
            })
            ->when($name, fn ($query) => $query->where('name', 'like', "%{$name}%"))
            ->when($customerId, fn ($query) => $query->where('customer_id', $customerId))
            ->when($typeId, fn ($query) => $query->where('integrationtype_id', $typeId))
            ->when($hasPriorityColumn && $priority !== null && $priority !== '', fn ($query) => $query->where('priority', (int) $priority))
            ->when($hasPriorityColumn, fn ($query) => $query->orderByDesc('priority'))
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        $customers = Customer::orderBy('name')->get(['id', 'name']);
        $types = Integrationtype::orderBy('name')->get(['id', 'name']);

        return view('integrations.index', compact('integrations', 'q', 'name', 'customerId', 'typeId', 'priority', 'hasPriorityColumn', 'customers', 'types'));
    }

    public function create()
    {
        $integration = new Integration();
        $customers = Customer::orderBy('name')->get(['id', 'name']);
        $types = Integrationtype::orderBy('name')->get(['id', 'name']);
        $leadFields = $this->leadFields();
        $kommoPipelineConditions = collect();
        $atomWebhooks = collect();
        $atomConditions = collect();
        $letyWebhooks = collect();
        $letyConditions = collect();
        $freshworksVariableMappings = collect();
        $integrationVariableMappings = collect();

        return view('integrations.create', compact('integration', 'customers', 'types', 'leadFields', 'kommoPipelineConditions', 'atomWebhooks', 'atomConditions', 'letyWebhooks', 'letyConditions', 'freshworksVariableMappings', 'integrationVariableMappings'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules($request));
        $payload = $this->normalizePayloadByType($validated);
        $payload = $this->hydrateZohoTokensFromAuthorizationCode($payload);
        $payload = $this->hydrateSalesforceTokenFromCredentials($payload);
        $payload['public_key'] = $this->generatePublicKey();

        $integration = Integration::create($payload);
        $integration->load('integrationtype');
        $this->syncKommoPipelineConditions($integration, $validated);
        $this->syncAtomConfiguration($integration, $validated);
        $this->syncLetyConfiguration($integration, $validated);
        $this->syncFreshworksVariableMappings($integration, $validated);
        $this->syncIntegrationVariableMappings($integration, $validated);

        return redirect()
            ->route('integrations.show', $integration)
            ->with('success', 'Integracion creada correctamente.');
    }

    public function show(Integration $integration)
    {
        $integration->load(['customer:id,name', 'integrationtype:id,name']);

        if ($this->normalizeIntegrationTypeName(optional($integration->integrationtype)->name) === 'monday') {
            $integration->load([
                'mondayBoards' => fn ($query) => $query
                    ->withCount(['groups', 'columns', 'columnMappings'])
                    ->orderBy('name'),
            ]);
        }

        if ($this->normalizeIntegrationTypeName(optional($integration->integrationtype)->name) === 'kommopipeline') {
            $integration->load([
                'kommoPipelineConditions' => fn ($query) => $query->orderBy('order')->orderBy('id'),
            ]);
        }

        if ($this->normalizeIntegrationTypeName(optional($integration->integrationtype)->name) === 'atom') {
            $integration->load([
                'atomWebhooks' => fn ($query) => $query->orderBy('order')->orderBy('id'),
                'atomConditions' => fn ($query) => $query->with('webhook')->orderBy('order')->orderBy('id'),
            ]);
        }

        if ($this->normalizeIntegrationTypeName(optional($integration->integrationtype)->name) === 'lety') {
            $integration->load([
                'letyWebhooks' => fn ($query) => $query->orderBy('order')->orderBy('id'),
                'letyConditions' => fn ($query) => $query->with('webhook')->orderBy('order')->orderBy('id'),
            ]);
        }

        if ($this->normalizeIntegrationTypeName(optional($integration->integrationtype)->name) === 'freshworks') {
            $integration->load([
                'freshworksVariableMappings' => fn ($query) => $query->orderBy('order')->orderBy('id'),
            ]);
        }

        if (in_array($this->normalizeIntegrationTypeName(optional($integration->integrationtype)->name), self::VARIABLE_MAPPING_INTEGRATION_TYPES, true)) {
            $integration->load([
                'variableMappings' => fn ($query) => $query->orderBy('order')->orderBy('id'),
            ]);
        }

        return view('integrations.show', compact('integration'));
    }

    public function edit(Integration $integration)
    {
        $customers = Customer::orderBy('name')->get(['id', 'name']);
        $types = Integrationtype::orderBy('name')->get(['id', 'name']);
        $leadFields = $this->leadFields();

        $integration->loadMissing([
            'kommoPipelineConditions' => fn ($query) => $query->orderBy('order')->orderBy('id'),
            'atomWebhooks' => fn ($query) => $query->orderBy('order')->orderBy('id'),
            'atomConditions' => fn ($query) => $query->orderBy('order')->orderBy('id'),
            'letyWebhooks' => fn ($query) => $query->orderBy('order')->orderBy('id'),
            'letyConditions' => fn ($query) => $query->orderBy('order')->orderBy('id'),
            'freshworksVariableMappings' => fn ($query) => $query->orderBy('order')->orderBy('id'),
            'variableMappings' => fn ($query) => $query->orderBy('order')->orderBy('id'),
        ]);

        $kommoPipelineConditions = $integration->kommoPipelineConditions;
        $atomWebhooks = $integration->atomWebhooks;
        $atomConditions = $integration->atomConditions;
        $letyWebhooks = $integration->letyWebhooks;
        $letyConditions = $integration->letyConditions;
        $freshworksVariableMappings = $integration->freshworksVariableMappings;
        $integrationVariableMappings = $integration->variableMappings;

        return view('integrations.edit', compact('integration', 'customers', 'types', 'leadFields', 'kommoPipelineConditions', 'atomWebhooks', 'atomConditions', 'letyWebhooks', 'letyConditions', 'freshworksVariableMappings', 'integrationVariableMappings'));
    }

    public function update(Request $request, Integration $integration)
    {
        $validated = $request->validate($this->rules($request, true, $integration));
        $integration->loadMissing('integrationtype:id,name');

        $typeName = $this->normalizeIntegrationTypeName(
            Integrationtype::whereKey($validated['integrationtype_id'])->value('name')
        );

        $validated = $this->preserveExistingSensitiveValues($validated, $integration, $typeName);

        $payload = $this->normalizePayloadByType($validated);
        $payload = $this->hydrateSalesforceTokenFromCredentials($payload);

        if ($request->boolean('regenerate_public_key')) {
            $payload['public_key'] = $this->generatePublicKey();
        }

        $integration->update($payload);
        $integration->load('integrationtype');
        $this->syncKommoPipelineConditions($integration, $validated);
        $this->syncAtomConfiguration($integration, $validated);
        $this->syncLetyConfiguration($integration, $validated);
        $this->syncFreshworksVariableMappings($integration, $validated);
        $this->syncIntegrationVariableMappings($integration, $validated);

        return redirect()
            ->route('integrations.show', $integration)
            ->with('success', 'Integracion actualizada.');
    }

    public function destroy(Integration $integration)
    {
        $integration->delete();

        return redirect()
            ->route('integrations.index')
            ->with('success', 'Integracion eliminada.');
    }

    public function kommoPipelinePipelines(Integration $integration)
    {
        $response = $this->kommoPipelineRequest($integration, '/api/v4/leads/pipelines');

        return response()->json($this->normalizeKommoPipelines($response->json()));
    }

    public function kommoPipelineStatuses(Integration $integration, string $pipelineId)
    {
        $response = $this->kommoPipelineRequest($integration, "/api/v4/leads/pipelines/{$pipelineId}/statuses");

        return response()->json($this->normalizeKommoStatuses($response->json()));
    }

    public function syncKommoBoards(Integration $integration, KommoPipelineSyncService $service): RedirectResponse
    {
        $integration->loadMissing('integrationtype:id,name');

        if (! $service->supports($integration)) {
            return redirect()
                ->route('integrations.show', $integration)
                ->withErrors(['sync' => 'Esta integracion no permite sincronizar tableros de Kommo.']);
        }

        try {
            $result = $service->syncCrmStates($integration);
        } catch (\Throwable $exception) {
            Log::error('KOMMO PIPELINE CRM STATES SYNC FAILED', [
                'integration_id' => $integration->id,
                'message' => $exception->getMessage(),
            ]);

            $message = $exception instanceof \RuntimeException
                ? $exception->getMessage()
                : 'No fue posible sincronizar los tableros de Kommo. Intentalo de nuevo o revisa los logs.';

            return redirect()
                ->route('integrations.show', $integration)
                ->withErrors(['sync' => $message]);
        }

        return redirect()
            ->route('integrations.show', $integration)
            ->with(
                'success',
                "Tableros sincronizados correctamente. Estados creados: {$result['created']}. Estados actualizados: {$result['updated']}."
            );
    }

    public function syncHubspotDealStages(Integration $integration, HubspotPipelineSyncService $service): RedirectResponse
    {
        $integration->loadMissing('integrationtype:id,name');

        if ($this->normalizeIntegrationTypeName(optional($integration->integrationtype)->name) !== 'hubspot') {
            return redirect()
                ->route('integrations.show', $integration)
                ->withErrors(['sync' => 'Esta integracion no permite sincronizar etapas de HubSpot.']);
        }

        try {
            $result = $service->syncCrmStates($integration);
        } catch (\Throwable $exception) {
            Log::error('HUBSPOT CRM STATES SYNC FAILED', [
                'integration_id' => $integration->id,
                'message' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('integrations.show', $integration)
                ->withErrors(['sync' => $exception->getMessage()]);
        }

        return redirect()
            ->route('integrations.show', $integration)
            ->with('success', "Etapas de HubSpot sincronizadas. Estados creados: {$result['created']}. Estados actualizados: {$result['updated']}.");
    }

    private function rules(Request $request, bool $updating = false, ?Integration $integration = null): array
    {
        $typeName = $this->normalizeIntegrationTypeName(
            Integrationtype::whereKey($request->input('integrationtype_id'))->value('name')
        );

        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'integrationtype_id' => 'required|exists:integrationtypes,id',
            'customer_id' => 'required|exists:customers,id',
            'url' => (in_array($typeName, ['gohighlevel', 'gohighlevel_oportunidad', 'atom', 'lety'], true) ? 'nullable' : 'required').'|url',
            'urldestino' => ['nullable', 'url', 'max:2048'],
            'status' => 'required|boolean',
            'crm_Id_phone' => ['nullable', 'string', 'max:255'],
            'crm_Id_service' => ['nullable', 'string', 'max:255'],
            'crm_Id_fuente' => ['nullable', 'string', 'max:255'],
            'crm_Id_email' => ['nullable', 'string', 'max:255'],
            'disable_integration_id_crm_prefix' => ['nullable', 'boolean'],
            'crm_id_prefix' => ['nullable', 'string', 'max:255'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string'],
            'code' => ['nullable', 'string'],
            'access_token' => ['nullable', 'string'],
            'refresh_token' => ['nullable', 'string'],
            'api_domain' => ['nullable', 'url', 'max:255'],
            'scope' => ['nullable', 'string'],
            'token_type' => ['nullable', 'string', 'max:30'],
            'expires_in' => ['nullable', 'integer', 'min:1'],
            'token_expires_at' => ['nullable', 'date'],
            'territory_id' => ['nullable', 'string', 'max:255'],
            'owner_id' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'lead_source_id' => ['nullable', 'string', 'max:255'],
            'custom_field' => ['nullable', 'string'],
            'tokent' => ['nullable', 'string'],
            'url_credenciales' => ['nullable', 'url', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'body_oportunidad' => ['nullable', 'string'],
            'url_consulta_lead' => ['nullable', 'url', 'max:255'],
            'url_negocio' => ['nullable', 'url', 'max:255'],
            'url_creacionlead' => ['nullable', 'url', 'max:255'],
            'dealname' => ['nullable', 'string'],
            'dealstage' => ['nullable', 'string', 'max:255'],
            'kommo_pipeline_default_pipeline_id' => ['nullable', 'string', 'max:255', 'required_with:kommo_pipeline_default_status_id'],
            'kommo_pipeline_default_pipeline_name' => ['nullable', 'string', 'max:255'],
            'kommo_pipeline_default_status_id' => ['nullable', 'string', 'max:255', 'required_with:kommo_pipeline_default_pipeline_id'],
            'kommo_pipeline_default_status_name' => ['nullable', 'string', 'max:255'],
            'kommo_pipeline_conditions' => ['nullable', 'array'],
            'kommo_pipeline_conditions.*.lead_field' => ['required_with:kommo_pipeline_conditions', 'string', Rule::in($this->leadFields())],
            'kommo_pipeline_conditions.*.expected_value' => ['required_with:kommo_pipeline_conditions', 'string', 'max:255'],
            'kommo_pipeline_conditions.*.pipeline_id' => ['required_with:kommo_pipeline_conditions', 'string', 'max:255'],
            'kommo_pipeline_conditions.*.pipeline_name' => ['nullable', 'string', 'max:255'],
            'kommo_pipeline_conditions.*.status_id' => ['required_with:kommo_pipeline_conditions', 'string', 'max:255'],
            'kommo_pipeline_conditions.*.status_name' => ['nullable', 'string', 'max:255'],
            'kommo_pipeline_conditions.*.order' => ['nullable', 'integer', 'min:0'],
            'kommo_pipeline_conditions.*.active' => ['nullable', 'boolean'],
            'atom_webhooks' => ['nullable', 'array'],
            'atom_webhooks.*.key' => ['required_with:atom_webhooks', 'string', 'max:80'],
            'atom_webhooks.*.name' => ['required_with:atom_webhooks', 'string', 'max:255'],
            'atom_webhooks.*.url' => ['required_with:atom_webhooks', 'url', 'max:255'],
            'atom_webhooks.*.order' => ['nullable', 'integer', 'min:0'],
            'atom_webhooks.*.active' => ['nullable', 'boolean'],
            'atom_webhooks.*.is_default' => ['nullable', 'boolean'],
            'atom_conditions' => ['nullable', 'array'],
            'atom_conditions.*.lead_field' => ['required_with:atom_conditions', 'string', Rule::in($this->leadFields())],
            'atom_conditions.*.expected_value' => ['required_with:atom_conditions', 'string', 'max:255'],
            'atom_conditions.*.webhook_key' => ['required_with:atom_conditions', 'string', 'max:80'],
            'atom_conditions.*.order' => ['nullable', 'integer', 'min:0'],
            'atom_conditions.*.active' => ['nullable', 'boolean'],
            'lety_webhooks' => ['nullable', 'array'],
            'lety_webhooks.*.key' => ['required_with:lety_webhooks', 'string', 'max:80'],
            'lety_webhooks.*.name' => ['required_with:lety_webhooks', 'string', 'max:255'],
            'lety_webhooks.*.url' => ['required_with:lety_webhooks', 'url', 'max:255'],
            'lety_webhooks.*.body' => ['required_with:lety_webhooks', 'string'],
            'lety_webhooks.*.order' => ['nullable', 'integer', 'min:0'],
            'lety_webhooks.*.active' => ['nullable', 'boolean'],
            'lety_conditions' => ['nullable', 'array'],
            'lety_conditions.*.lead_field' => ['required_with:lety_conditions', 'string', Rule::in($this->leadFields())],
            'lety_conditions.*.expected_value' => ['required_with:lety_conditions', 'string', 'max:255'],
            'lety_conditions.*.webhook_key' => ['required_with:lety_conditions', 'string', 'max:80'],
            'lety_conditions.*.order' => ['nullable', 'integer', 'min:0'],
            'lety_conditions.*.active' => ['nullable', 'boolean'],
            'freshworks_variable_mappings' => ['nullable', 'array'],
            'freshworks_variable_mappings.*.target_variable' => ['required_with:freshworks_variable_mappings', 'string', 'max:255'],
            'freshworks_variable_mappings.*.lead_field' => ['required_with:freshworks_variable_mappings', 'string', Rule::in($this->leadFields())],
            'freshworks_variable_mappings.*.expected_value' => ['required_with:freshworks_variable_mappings', 'string', 'max:255'],
            'freshworks_variable_mappings.*.mapped_value' => ['nullable', 'string'],
            'freshworks_variable_mappings.*.order' => ['nullable', 'integer', 'min:0'],
            'freshworks_variable_mappings.*.active' => ['nullable', 'boolean'],
            'integration_variable_mappings' => ['nullable', 'array'],
            'integration_variable_mappings.*.target_variable' => ['required_with:integration_variable_mappings', 'string', 'max:255'],
            'integration_variable_mappings.*.lead_field' => ['required_with:integration_variable_mappings', 'string', Rule::in($this->leadFields())],
            'integration_variable_mappings.*.expected_value' => ['required_with:integration_variable_mappings', 'string', 'max:255'],
            'integration_variable_mappings.*.mapped_value' => ['nullable', 'string'],
            'integration_variable_mappings.*.order' => ['nullable', 'integer', 'min:0'],
            'integration_variable_mappings.*.active' => ['nullable', 'boolean'],
        ];

        if ($this->hasIntegrationPriorityColumn()) {
            $rules['priority'] = ['required', 'integer', 'min:0'];
        }

        if ($typeName === 'freshworks') {
            $rules['tokent'] = $this->sensitiveValueRule($updating, $integration, $typeName, 'tokent');
            $rules['territory_id'] = ['required', 'string', 'max:255'];
            $rules['owner_id'] = ['required', 'string', 'max:255'];
            $rules['city'] = ['required', 'string', 'max:255'];
            $rules['lead_source_id'] = ['required', 'string', 'max:255'];
            $rules['custom_field'] = ['required', 'string'];
        }

        if ($typeName === 'zapnito_invitacion') {
            $rules['tokent'] = $this->sensitiveValueRule($updating, $integration, $typeName, 'tokent');
            $rules['body'] = ['required', 'string'];
        }

        if ($typeName === 'salesforce') {
            $rules['url_credenciales'] = ['required', 'url', 'max:255'];
            $rules['username'] = ['required', 'string', 'max:255'];
            $rules['password'] = $this->sensitiveValueRule($updating, $integration, $typeName, 'password');
            $rules['body'] = ['required', 'string'];
        }

        if ($typeName === 'monday') {
            $rules['tokent'] = $this->sensitiveValueRule($updating, $integration, $typeName, 'tokent');
        }

        if ($typeName === 'hubspot') {
            $rules['access_token'] = $this->sensitiveValueRule($updating, $integration, $typeName, 'access_token');
            $rules['body'] = ['required', 'string'];
            $rules['body_oportunidad'] = ['required', 'string'];
        }

        if (in_array($typeName, ['gohighlevel', 'gohighlevel_oportunidad'], true)) {
            $rules['tokent'] = $this->sensitiveValueRule($updating, $integration, $typeName, 'tokent');
            $rules['body'] = ['required', 'string'];
            if ($typeName === 'gohighlevel_oportunidad') {
                $rules['body_oportunidad'] = ['required', 'string'];
            }
        }

        if ($typeName === 'kommopipeline') {
            $rules['tokent'] = $this->sensitiveValueRule($updating, $integration, $typeName, 'tokent');
            $rules['body'] = ['required', 'string'];
        }

        if ($typeName === 'atom') {
            $rules['tokent'] = $this->sensitiveValueRule($updating, $integration, $typeName, 'tokent');
            $rules['body'] = ['required', 'string'];
        }

        if ($updating) {
            $rules['regenerate_public_key'] = 'nullable|boolean';
        }

        return $rules;
    }

    private function normalizePayloadByType(array $validated): array
    {
        $validated['status'] = array_key_exists('status', $validated) ? (int) $validated['status'] : 1;

        if ($this->hasIntegrationPriorityColumn()) {
            $validated['priority'] = array_key_exists('priority', $validated) ? (int) $validated['priority'] : 100;
        } else {
            unset($validated['priority']);
        }

        if (array_key_exists('access_token', $validated)) {
            $validated['tokent'] = $validated['access_token'];
            unset($validated['access_token']);
        }

        $typeName = $this->normalizeIntegrationTypeName(
            Integrationtype::whereKey($validated['integrationtype_id'])->value('name')
        );

        if (!in_array($typeName, ['hubspot', 'gohighlevel', 'gohighlevel_oportunidad', 'atom', 'lety'], true) && empty($validated['url'])) {
            throw ValidationException::withMessages([
                'url' => 'El campo URL es obligatorio.',
            ]);
        }

        if ($typeName === 'google_sheets') {
            $validated = $this->clearKommoFields($validated);
            $validated = $this->clearZohoFields($validated);
            $validated = $this->clearFreshworksFields($validated);
            $validated = $this->clearSalesforceFields($validated);
            $validated = $this->clearHubspotFields($validated);
            $validated = $this->clearCrmIdPrefixFields($validated);
        }

        if ($typeName === 'kommo') {
            $validated = $this->clearZohoFields($validated);
            $validated = $this->clearFreshworksFields($validated);
            $validated = $this->clearSalesforceFields($validated);
            $validated = $this->clearHubspotFields($validated);
            $validated = $this->validateKommoPayload($validated);
            $validated = $this->validateCrmIdPrefixPayload($validated, 'Kommo');
        }

        if ($typeName === 'kommopipeline') {
            $validated = $this->clearKommoFields($validated);
            $validated = $this->clearZohoFieldsPreservingToken($validated);
            $validated = $this->clearFreshworksFields($validated);
            $validated = $this->clearSalesforceCredentialFields($validated);
            $validated = $this->clearHubspotFields($validated);
            $validated = $this->validateKommoPipelinePayload($validated);
            $validated = $this->validateCrmIdPrefixPayload($validated, 'KommoPipeline');
        }

        if ($typeName === 'atom') {
            $validated['url'] = null;
            $validated = $this->clearKommoFields($validated);
            $validated = $this->clearZohoFieldsPreservingToken($validated);
            $validated = $this->clearFreshworksFields($validated);
            $validated = $this->clearSalesforceCredentialFields($validated);
            $validated = $this->clearHubspotFields($validated);
            $validated = $this->clearCrmIdPrefixFields($validated);
            $validated = $this->validateAtomPayload($validated);
        }

        if ($typeName === 'lety') {
            $validated['url'] = null;
            $validated['tokent'] = null;
            $validated['body'] = null;
            $validated = $this->clearKommoFields($validated);
            $validated = $this->clearZohoFields($validated);
            $validated = $this->clearFreshworksFields($validated);
            $validated = $this->clearSalesforceFields($validated);
            $validated = $this->clearHubspotFields($validated);
            $validated = $this->clearCrmIdPrefixFields($validated);
            $validated = $this->validateLetyPayload($validated);
        }

        if ($typeName === 'zoho') {
            $validated = $this->clearKommoFields($validated);
            $validated = $this->clearFreshworksFields($validated);
            $validated = $this->clearSalesforceFields($validated);
            $validated = $this->clearHubspotFields($validated);
            $validated = $this->clearCrmIdPrefixFields($validated);
        }

        if ($typeName === 'freshworks') {
            $validated = $this->clearKommoFields($validated);
            $validated = $this->clearZohoFieldsPreservingToken($validated);
            $validated = $this->clearSalesforceFields($validated);
            $validated = $this->clearHubspotFields($validated);
            $validated = $this->validateFreshworksPayload($validated);
            $validated = $this->validateCrmIdPrefixPayload($validated, 'Freshworks');
        }

        if ($typeName === 'zapnito_invitacion') {
            $validated = $this->clearKommoFields($validated);
            $validated = $this->clearZohoFieldsPreservingToken($validated);
            $validated = $this->clearFreshworksFields($validated);
            $validated = $this->clearSalesforceCredentialFields($validated);
            $validated = $this->clearHubspotFields($validated);
            $validated = $this->validateZapnitoInvitationPayload($validated);
            $validated = $this->clearCrmIdPrefixFields($validated);
        }

        if ($typeName === 'salesforce') {
            $validated = $this->clearKommoFields($validated);
            $validated = $this->clearZohoFieldsPreservingToken($validated);
            $validated = $this->clearFreshworksFields($validated);
            $validated = $this->clearHubspotFields($validated);
            $validated = $this->validateSalesforcePayload($validated);
            $validated = $this->clearCrmIdPrefixFields($validated);
        }

        if ($typeName === 'monday') {
            $validated = $this->clearKommoFields($validated);
            $validated = $this->clearZohoFieldsPreservingToken($validated);
            $validated = $this->clearFreshworksFields($validated);
            $validated = $this->clearSalesforceFields($validated);
            $validated = $this->clearHubspotFields($validated);
            $validated = $this->validateMondayPayload($validated);
            $validated = $this->clearCrmIdPrefixFields($validated);
        }

        if ($typeName === 'hubspot') {
            $validated = $this->clearKommoFields($validated);
            $validated = $this->clearZohoFieldsPreservingToken($validated);
            $validated = $this->clearFreshworksFields($validated);
            $validated = $this->clearSalesforceCredentialFields($validated);
            $validated = $this->validateHubspotPayload($validated);
            $validated = $this->validateCrmIdPrefixPayload($validated, 'HubSpot');
        }

        if (in_array($typeName, ['gohighlevel', 'gohighlevel_oportunidad'], true)) {
            $validated = $this->clearKommoFields($validated);
            $validated = $this->clearZohoFieldsPreservingToken($validated);
            $validated = $this->clearFreshworksFields($validated);
            $validated = $this->clearSalesforceCredentialFields($validated);
            if ($typeName === 'gohighlevel_oportunidad') {
                $validated['url_consulta_lead'] = null;
                $validated['url_negocio'] = null;
                $validated['url_creacionlead'] = null;
                $validated['dealname'] = null;
                $validated['dealstage'] = null;
            } else {
                $validated = $this->clearHubspotFields($validated);
            }
            $validated = $this->validateGohighlevelPayload($validated);
            if ($typeName === 'gohighlevel_oportunidad') {
                $validated = $this->validateGohighlevelOpportunityPayload($validated);
            }
            $validated = $this->validateCrmIdPrefixPayload($validated, 'GoHighLevel');
        }

        if (!in_array($typeName, ['google_sheets', 'kommo', 'kommopipeline', 'atom', 'lety', 'zoho', 'freshworks', 'zapnito_invitacion', 'salesforce', 'monday', 'hubspot', 'gohighlevel', 'gohighlevel_oportunidad'], true)) {
            $validated = $this->clearHubspotFields($validated);
            $validated = $this->clearCrmIdPrefixFields($validated);
        }

        if ($typeName !== 'kommopipeline') {
            $validated = $this->clearKommoPipelineFields($validated);
        }

        unset($validated['kommo_pipeline_conditions']);
        unset($validated['atom_webhooks'], $validated['atom_conditions'], $validated['lety_webhooks'], $validated['lety_conditions'], $validated['freshworks_variable_mappings'], $validated['integration_variable_mappings']);

        return $validated;
    }

    private function hasIntegrationPriorityColumn(): bool
    {
        return Schema::hasColumn('integrations', 'priority');
    }

    private function hasIntegrationDestinationUrlColumn(): bool
    {
        return Schema::hasColumn('integrations', 'urldestino');
    }

    private function sensitiveValueRule(bool $updating, ?Integration $integration, string $typeName, string $field): array
    {
        return $this->hasStoredSensitiveValue($updating, $integration, $typeName, $field)
            ? ['nullable', 'string']
            : ['required', 'string'];
    }

    private function hasStoredSensitiveValue(bool $updating, ?Integration $integration, string $typeName, string $field): bool
    {
        if (! $updating || ! $integration || ! $this->integrationTypeMatches($integration, $typeName)) {
            return false;
        }

        $storedField = $field === 'access_token' ? 'tokent' : $field;

        return filled($integration->{$storedField});
    }

    private function integrationTypeMatches(Integration $integration, string $typeName): bool
    {
        if ($typeName === '') {
            return false;
        }

        $existingTypeName = $this->normalizeIntegrationTypeName(optional($integration->integrationtype)->name);

        if ($existingTypeName === '') {
            $existingTypeName = $this->normalizeIntegrationTypeName(
                Integrationtype::whereKey($integration->integrationtype_id)->value('name')
            );
        }

        return $existingTypeName === $typeName;
    }

    private function preserveExistingSensitiveValues(array $validated, Integration $integration, string $typeName): array
    {
        if (! $this->integrationTypeMatches($integration, $typeName)) {
            return $validated;
        }

        foreach (['tokent', 'client_secret', 'refresh_token', 'password'] as $field) {
            if (array_key_exists($field, $validated) && blank($validated[$field]) && filled($integration->{$field})) {
                $validated[$field] = $integration->{$field};
            }
        }

        if (array_key_exists('access_token', $validated) && blank($validated['access_token']) && filled($integration->tokent)) {
            $validated['access_token'] = $integration->tokent;
        }

        return $validated;
    }

    private function normalizeIntegrationTypeName(?string $typeName): string
    {
        $normalized = Str::of((string) $typeName)
            ->ascii()
            ->lower()
            ->replace([' ', '-'], '_')
            ->replaceMatches('/_+/', '_')
            ->trim('_')
            ->toString();

        return match ($normalized) {
            'go_high_level', 'leadconnector', 'lead_connector' => 'gohighlevel',
            'gohighleve_oportunidad', 'gohighlevel_opportunity' => 'gohighlevel_oportunidad',
            'kommo_pipeline' => 'kommopipeline',
            'atom_webhook', 'atom_webhooks' => 'atom',
            'lety_webhook', 'lety_webhooks' => 'lety',
            'zapnito', 'zapnito_invitation', 'zapnito_invitations' => 'zapnito_invitacion',
            default => $normalized,
        };
    }

    private function validateFreshworksPayload(array $payload): array
    {
        $required = [
            'tokent' => 'token',
            'territory_id' => 'territory_id',
            'owner_id' => 'owner_id',
            'city' => 'City',
            'lead_source_id' => 'lead_source_id',
            'custom_field' => 'custom_field',
        ];

        $messages = [];
        foreach ($required as $field => $label) {
            if (empty($payload[$field])) {
                $messages[$field] = "Para Freshworks el campo {$label} es obligatorio.";
            }
        }

        if (!empty($payload['custom_field']) && !$this->isValidFreshworksCustomField($payload['custom_field'])) {
            $messages['custom_field'] = 'custom_field debe ser un JSON valido.';
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }

        return $payload;
    }

    private function validateZapnitoInvitationPayload(array $payload): array
    {
        $required = [
            'url' => 'URL base Zapnito',
            'tokent' => 'token',
            'body' => 'body',
        ];

        $messages = [];
        foreach ($required as $field => $label) {
            if (empty($payload[$field])) {
                $messages[$field] = "Para Zapnito invitacion el campo {$label} es obligatorio.";
            }
        }

        if (!empty($payload['body']) && !$this->isValidJsonTemplate($payload['body'], '__zapnito_lead_field__:', true)) {
            $messages['body'] = 'body debe ser un JSON valido y solo acepta variables {{$lead->campo}} o {{$lead.campo}}.';
        }

        if (!empty($payload['url'])) {
            $payload['url'] = $this->normalizeZapnitoBaseUrl((string) $payload['url']);
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }

        return $payload;
    }

    private function validateKommoPayload(array $payload): array
    {
        return $payload;
    }

    private function validateKommoPipelinePayload(array $payload): array
    {
        $messages = [];

        foreach ([
            'url' => 'URL base de Kommo',
            'tokent' => 'token de acceso',
            'body' => 'payload JSON configurable',
        ] as $field => $label) {
            if (empty($payload[$field])) {
                $messages[$field] = "Para KommoPipeline el campo {$label} es obligatorio.";
            }
        }

        if (!empty($payload['body']) && !$this->isValidKommoPipelineJsonTemplate($payload['body'])) {
            $messages['body'] = 'El payload JSON debe ser valido y solo acepta {{$lead->campo}}, {{pipeline_id}} y {{status_id}}.';
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }

        return $payload;
    }

    private function validateAtomPayload(array $payload): array
    {
        $messages = [];
        $webhooks = collect($payload['atom_webhooks'] ?? [])
            ->filter(fn ($webhook) => filled($webhook['key'] ?? null))
            ->values();
        $conditions = collect($payload['atom_conditions'] ?? [])
            ->filter(fn ($condition) => filled($condition['lead_field'] ?? null))
            ->values();

        if (empty($payload['tokent'])) {
            $messages['tokent'] = 'Para Atom el token de autenticacion es obligatorio.';
        }

        if (empty($payload['body'])) {
            $messages['body'] = 'Para Atom el body JSON es obligatorio.';
        } elseif (!$this->isValidAtomJsonTemplate($payload['body'])) {
            $messages['body'] = 'El body Atom debe ser JSON valido y solo acepta variables {{$lead->campo}} o {{$lead.campo}}.';
        }

        if ($webhooks->isEmpty()) {
            $messages['atom_webhooks'] = 'Para Atom debes configurar al menos un webhook.';
        }

        $defaultWebhooks = $webhooks->filter(fn ($webhook) => (bool) ($webhook['is_default'] ?? false) && (bool) ($webhook['active'] ?? true));

        if ($defaultWebhooks->isEmpty()) {
            $messages['atom_webhooks'] = 'Para Atom debes marcar un webhook por defecto.';
        } elseif ($defaultWebhooks->count() > 1) {
            $messages['atom_webhooks'] = 'Para Atom solo debes marcar un webhook por defecto.';
        }

        if ($conditions->isEmpty()) {
            $messages['atom_conditions'] = 'Para Atom debes configurar al menos una condicion.';
        }

        $webhookKeys = $webhooks
            ->pluck('key')
            ->map(fn ($key) => (string) $key)
            ->all();

        foreach ($conditions as $index => $condition) {
            if (!in_array((string) ($condition['webhook_key'] ?? ''), $webhookKeys, true)) {
                $messages["atom_conditions.{$index}.webhook_key"] = 'La condicion Atom debe apuntar a un webhook configurado.';
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }

        return $payload;
    }

    private function validateLetyPayload(array $payload): array
    {
        $messages = [];
        $webhooks = collect($payload['lety_webhooks'] ?? [])
            ->filter(fn ($webhook) => filled($webhook['key'] ?? null))
            ->values();
        $conditions = collect($payload['lety_conditions'] ?? [])
            ->filter(fn ($condition) => filled($condition['lead_field'] ?? null))
            ->values();

        if ($webhooks->isEmpty()) {
            $messages['lety_webhooks'] = 'Para Lety debes configurar al menos un webhook.';
        }

        if ($conditions->isEmpty()) {
            $messages['lety_conditions'] = 'Para Lety debes configurar al menos una condicion.';
        }

        $webhookKeys = $webhooks
            ->pluck('key')
            ->map(fn ($key) => (string) $key)
            ->all();

        foreach ($webhooks as $index => $webhook) {
            if (empty($webhook['body'])) {
                $messages["lety_webhooks.{$index}.body"] = 'El payload de Lety es obligatorio.';
                continue;
            }

            if (!$this->isValidLetyFormTemplate((string) $webhook['body'])) {
                $messages["lety_webhooks.{$index}.body"] = 'El payload de Lety debe usar lineas campo=valor y solo acepta variables {{$lead->campo}} o {{$lead.campo}}.';
            }
        }

        foreach ($conditions as $index => $condition) {
            if (!in_array((string) ($condition['webhook_key'] ?? ''), $webhookKeys, true)) {
                $messages["lety_conditions.{$index}.webhook_key"] = 'La condicion Lety debe apuntar a un webhook configurado.';
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }

        return $payload;
    }

    private function validateSalesforcePayload(array $payload): array
    {
        $required = [
            'url_credenciales' => 'url_credenciales',
            'username' => 'Client ID / Consumer Key',
            'password' => 'Client Secret / Consumer Secret',
            'body' => 'body',
        ];

        $messages = [];
        foreach ($required as $field => $label) {
            if (empty($payload[$field])) {
                $messages[$field] = "Para Salesforce el campo {$label} es obligatorio.";
            }
        }

        if (!empty($payload['body']) && !is_array(json_decode($payload['body'], true))) {
            $messages['body'] = 'body debe ser un JSON valido.';
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }

        return $payload;
    }

    private function validateMondayPayload(array $payload): array
    {
        if (empty($payload['tokent'])) {
            throw ValidationException::withMessages([
                'tokent' => 'Para Monday el campo authorization es obligatorio.',
            ]);
        }

        return $payload;
    }

    private function validateHubspotPayload(array $payload): array
    {
        $required = [
            'tokent' => 'access_token',
            'url' => 'URL base HubSpot',
            'body' => 'body',
            'body_oportunidad' => 'body_oportunidad',
        ];

        $messages = [];
        foreach ($required as $field => $label) {
            if (empty($payload[$field])) {
                $messages[$field] = "Para HubSpot el campo {$label} es obligatorio.";
            }
        }

        $baseUrl = $this->normalizeHubspotBaseUrl((string) ($payload['url'] ?? ''));
        $payload['url'] = $baseUrl;
        $payload['url_consulta_lead'] = $baseUrl . '/crm/v3/objects/contacts/search';
        $payload['url_creacionlead'] = $baseUrl . '/crm/v3/objects/contacts';
        $payload['url_negocio'] = $baseUrl . '/crm/v3/objects/deals';

        if (!empty($payload['body']) && !$this->isValidJsonTemplate($payload['body'])) {
            $messages['body'] = 'body debe ser un JSON valido.';
        }

        if (!empty($payload['body_oportunidad']) && !$this->isValidJsonTemplate($payload['body_oportunidad'])) {
            $messages['body_oportunidad'] = 'body_oportunidad debe ser un JSON valido.';
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }

        return $payload;
    }

    private function normalizeHubspotBaseUrl(string $url): string
    {
        $parts = parse_url(trim($url));

        if (!isset($parts['scheme'], $parts['host'])) {
            throw ValidationException::withMessages([
                'url' => 'La URL base de HubSpot debe incluir protocolo y dominio.',
            ]);
        }

        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    private function normalizeZapnitoBaseUrl(string $url): string
    {
        $parts = parse_url(trim($url));

        if (!isset($parts['scheme'], $parts['host'])) {
            throw ValidationException::withMessages([
                'url' => 'La URL base de Zapnito debe incluir protocolo y dominio.',
            ]);
        }

        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    private function validateGohighlevelPayload(array $payload): array
    {
        $required = [
            'tokent' => 'token de LeadConnector / GoHighLevel',
            'body' => 'body JSON template',
        ];

        $messages = [];
        foreach ($required as $field => $label) {
            if (empty($payload[$field])) {
                $messages[$field] = "Para GoHighLevel el campo {$label} es obligatorio.";
            }
        }

        if (empty($payload['url'])) {
            $payload['url'] = self::DEFAULT_GOHIGHLEVEL_URL;
        }

        if (!empty($payload['body']) && !$this->isValidGohighlevelJsonTemplate($payload['body'])) {
            $messages['body'] = 'body debe ser un JSON valido y solo acepta variables {{$lead->campo}} simples.';
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }

        return $payload;
    }

    private function validateGohighlevelOpportunityPayload(array $payload): array
    {
        if (empty($payload['body_oportunidad'])) {
            throw ValidationException::withMessages([
                'body_oportunidad' => 'Para GoHighLevel-Oportunidad el campo body_oportunidad es obligatorio.',
            ]);
        }

        if (! $this->isValidGohighlevelJsonTemplate($payload['body_oportunidad'], ['contactId'])) {
            throw ValidationException::withMessages([
                'body_oportunidad' => 'body_oportunidad debe ser un JSON valido y solo acepta variables {{$lead->campo}} simples o {{contactId}}.',
            ]);
        }

        return $payload;
    }

    private function hydrateZohoTokensFromAuthorizationCode(array $payload): array
    {
        $typeName = strtolower((string) Integrationtype::whereKey($payload['integrationtype_id'])->value('name'));
        if ($typeName !== 'zoho') {
            return $payload;
        }

        if (empty($payload['client_id']) || empty($payload['client_secret']) || empty($payload['code'])) {
            throw ValidationException::withMessages([
                'code' => 'Para Zoho debes enviar client_id, client_secret y code para generar tokens.',
            ]);
        }

        $accountsUrl = rtrim((string) $payload['url'], '/');
        $query = [
            'grant_type' => 'authorization_code',
            'client_id' => trim((string) $payload['client_id']),
            'client_secret' => trim((string) $payload['client_secret']),
            'code' => trim((string) $payload['code']),
        ];

        $response = Http::acceptJson()->post($accountsUrl . '/oauth/v2/token?' . http_build_query($query));
        $json = $response->json();

        if (!$response->successful() || !is_array($json) || isset($json['error']) || empty($json['access_token'])) {
            $message = is_array($json)
                ? ($json['error'] ?? 'No fue posible obtener tokens de Zoho.')
                : 'No fue posible obtener tokens de Zoho.';

            throw ValidationException::withMessages([
                'code' => 'Zoho OAuth error: ' . $message,
            ]);
        }

        $expiresIn = isset($json['expires_in']) ? (int) $json['expires_in'] : null;

        $payload['tokent'] = (string) $json['access_token'];
        $payload['refresh_token'] = $json['refresh_token'] ?? ($payload['refresh_token'] ?? null);
        $payload['scope'] = $json['scope'] ?? null;
        $payload['api_domain'] = $json['api_domain'] ?? ($payload['api_domain'] ?? null);
        $payload['token_type'] = $json['token_type'] ?? null;
        $payload['expires_in'] = $expiresIn;
        $payload['token_expires_at'] = $expiresIn ? now()->addSeconds($expiresIn) : null;

        return $payload;
    }

    private function hydrateSalesforceTokenFromCredentials(array $payload): array
    {
        $typeName = strtolower((string) Integrationtype::whereKey($payload['integrationtype_id'])->value('name'));
        if ($typeName !== 'salesforce') {
            return $payload;
        }

        $response = Http::acceptJson()
            ->asForm()
            ->withBasicAuth((string) $payload['username'], (string) $payload['password'])
            ->post(rtrim((string) $payload['url_credenciales'], '/'), [
                'grant_type' => 'client_credentials',
            ]);

        $json = $response->json();

        if (!$response->successful() || !is_array($json) || isset($json['error']) || empty($json['access_token'])) {
            $message = is_array($json)
                ? ($json['error'] ?? 'No fue posible obtener token de Salesforce.')
                : 'No fue posible obtener token de Salesforce.';

            throw ValidationException::withMessages([
                'username' => 'Salesforce auth error: ' . $message,
            ]);
        }

        $expiresIn = isset($json['expires_in']) ? (int) $json['expires_in'] : null;

        $payload['tokent'] = (string) $json['access_token'];
        $payload['scope'] = $json['scope'] ?? null;
        $payload['token_type'] = $json['token_type'] ?? null;
        $payload['expires_in'] = $expiresIn;
        $payload['token_expires_at'] = $expiresIn ? now()->addSeconds($expiresIn) : null;

        return $payload;
    }

    private function clearKommoFields(array $payload): array
    {
        $payload['crm_Id_phone'] = null;
        $payload['crm_Id_email'] = null;
        $payload['crm_Id_service'] = null;
        $payload['crm_Id_fuente'] = null;

        return $payload;
    }

    private function clearZohoFields(array $payload): array
    {
        $payload['client_id'] = null;
        $payload['client_secret'] = null;
        $payload['code'] = null;
        $payload['refresh_token'] = null;
        $payload['tokent'] = null;
        $payload['api_domain'] = null;
        $payload['scope'] = null;
        $payload['token_type'] = null;
        $payload['expires_in'] = null;
        $payload['token_expires_at'] = null;

        return $payload;
    }

    private function clearZohoFieldsPreservingToken(array $payload): array
    {
        $payload['client_id'] = null;
        $payload['client_secret'] = null;
        $payload['code'] = null;
        $payload['refresh_token'] = null;
        $payload['api_domain'] = null;
        $payload['scope'] = null;
        $payload['token_type'] = null;
        $payload['expires_in'] = null;
        $payload['token_expires_at'] = null;

        return $payload;
    }

    private function clearFreshworksFields(array $payload): array
    {
        $payload['territory_id'] = null;
        $payload['owner_id'] = null;
        $payload['city'] = null;
        $payload['lead_source_id'] = null;
        $payload['custom_field'] = null;

        return $payload;
    }

    private function clearCrmIdPrefixFields(array $payload): array
    {
        $payload['disable_integration_id_crm_prefix'] = null;
        $payload['crm_id_prefix'] = null;

        return $payload;
    }

    private function validateCrmIdPrefixPayload(array $payload, string $integrationLabel): array
    {
        $payload['disable_integration_id_crm_prefix'] = array_key_exists('disable_integration_id_crm_prefix', $payload)
            ? (int) ((bool) $payload['disable_integration_id_crm_prefix'])
            : 0;

        $payload['crm_id_prefix'] = isset($payload['crm_id_prefix'])
            ? trim((string) $payload['crm_id_prefix'])
            : null;

        if ((int) $payload['disable_integration_id_crm_prefix'] === 1 && $payload['crm_id_prefix'] === '') {
            throw ValidationException::withMessages([
                'crm_id_prefix' => "Para {$integrationLabel} el prefijo manual es obligatorio cuando se desactiva el ID de integración en el crm_id.",
            ]);
        }

        if ($payload['crm_id_prefix'] === '') {
            $payload['crm_id_prefix'] = null;
        }

        return $payload;
    }

    private function isValidFreshworksCustomField(?string $customField): bool
    {
        $customField = trim((string) $customField);

        if ($customField === '') {
            return true;
        }

        return $this->isValidJsonTemplate($customField, '__freshworks_lead_field__:');
    }

    private function isValidJsonTemplate(?string $value, string $tokenPrefix = '__integration_lead_field__:', bool $rejectUnresolvedPlaceholders = false): bool
    {
        $value = trim((string) $value);

        if ($value === '') {
            return true;
        }

        $quotedPattern = '/"(\s*\{\{\s*([^}]+?)\s*\}\}\s*)"/';
        $inlinePattern = '/\{\{\s*([^}]+?)\s*\}\}/';

        $normalized = preg_replace_callback($quotedPattern, function ($matches) use ($tokenPrefix) {
            $path = $this->normalizeFreshworksPlaceholderPath($matches[2]);

            return $path === null
                ? $matches[0]
                : json_encode($tokenPrefix . $path, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $value);

        $normalized = preg_replace_callback($inlinePattern, function ($matches) use ($tokenPrefix) {
            $path = $this->normalizeFreshworksPlaceholderPath($matches[1]);

            return $path === null
                ? $matches[0]
                : json_encode($tokenPrefix . $path, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $normalized);

        return (!$rejectUnresolvedPlaceholders || !preg_match('/\{\{.*?\}\}/s', $normalized))
            && is_array(json_decode($normalized, true));
    }

    private function isValidGohighlevelJsonTemplate(?string $value, array $allowedContextVariables = []): bool
    {
        $value = trim((string) $value);

        if ($value === '') {
            return true;
        }

        $quotedPattern = '/"(\s*\{\{\s*([^}]+?)\s*\}\}\s*)"/';
        $inlinePattern = '/\{\{\s*([^}]+?)\s*\}\}/';

        $normalized = preg_replace_callback($quotedPattern, function ($matches) use ($allowedContextVariables) {
            $field = $this->normalizeGohighlevelPlaceholderField($matches[2], $allowedContextVariables);

            return $field === null
                ? $matches[0]
                : json_encode('__gohighlevel_lead_field__:' . $field, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $value);

        $normalized = preg_replace_callback($inlinePattern, function ($matches) use ($allowedContextVariables) {
            $field = $this->normalizeGohighlevelPlaceholderField($matches[1], $allowedContextVariables);

            return $field === null
                ? $matches[0]
                : json_encode('__gohighlevel_lead_field__:' . $field, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $normalized);

        return !preg_match('/\{\{.*?\}\}/s', $normalized)
            && is_array(json_decode($normalized, true));
    }

    private function isValidKommoPipelineJsonTemplate(?string $value): bool
    {
        $value = trim((string) $value);

        if ($value === '') {
            return true;
        }

        preg_match_all('/\{\{\s*([^}]+?)\s*\}\}/', $value, $matches);

        foreach ($matches[1] ?? [] as $expression) {
            $expression = trim($expression);

            if (in_array($expression, ['pipeline_id', 'status_id'], true)) {
                continue;
            }

            if (
                preg_match('/^\$?lead\s*(?:->|\.)\s*([A-Za-z_][A-Za-z0-9_]*)\s*$/', $expression, $leadMatches)
                && in_array($leadMatches[1], $this->leadFields(), true)
            ) {
                continue;
            }

            return false;
        }

        return is_array(json_decode($this->quoteKommoPipelineUnquotedPlaceholders($value), true));
    }

    private function isValidAtomJsonTemplate(?string $value): bool
    {
        $value = trim((string) $value);

        if ($value === '') {
            return true;
        }

        preg_match_all('/\{\{\s*([^}]+?)\s*\}\}/', $value, $matches);

        foreach ($matches[1] ?? [] as $expression) {
            $expression = trim($expression);

            if (
                preg_match('/^\$?lead\s*(?:->|\.)\s*([A-Za-z_][A-Za-z0-9_]*)\s*$/', $expression, $leadMatches)
                && in_array($leadMatches[1], $this->leadFields(), true)
            ) {
                continue;
            }

            return false;
        }

        return is_array(json_decode($this->quoteKommoPipelineUnquotedPlaceholders($value), true));
    }

    private function isValidLetyFormTemplate(?string $value): bool
    {
        $value = trim((string) $value);

        if ($value === '') {
            return false;
        }

        if (!$this->containsOnlySupportedLeadPlaceholders($value)) {
            return false;
        }

        $pairs = preg_split('/(?:\r\n|\r|\n|&)+/', $value) ?: [];
        $hasPair = false;

        foreach ($pairs as $pair) {
            $pair = trim($pair);

            if ($pair === '') {
                continue;
            }

            if (!str_contains($pair, '=')) {
                return false;
            }

            [$key] = explode('=', $pair, 2);
            $key = trim(rawurldecode($key));

            if (!preg_match('/^[A-Za-z0-9_.\-\[\]]+$/', $key)) {
                return false;
            }

            $hasPair = true;
        }

        return $hasPair;
    }

    private function containsOnlySupportedLeadPlaceholders(string $value): bool
    {
        preg_match_all('/\{\{\s*([^}]+?)\s*\}\}/', $value, $matches);

        foreach ($matches[1] ?? [] as $expression) {
            $expression = trim($expression);

            if (
                preg_match('/^\$?lead\s*(?:->|\.)\s*([A-Za-z_][A-Za-z0-9_]*)\s*$/', $expression, $leadMatches)
                && in_array($leadMatches[1], $this->leadFields(), true)
            ) {
                continue;
            }

            return false;
        }

        return true;
    }

    private function quoteKommoPipelineUnquotedPlaceholders(string $template): string
    {
        $result = '';
        $length = strlen($template);
        $inString = false;
        $escaped = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $template[$i];

            if ($char === '"' && !$escaped) {
                $inString = !$inString;
                $result .= $char;
                continue;
            }

            if (!$inString && $char === '{' && ($template[$i + 1] ?? null) === '{') {
                $end = strpos($template, '}}', $i + 2);

                if ($end !== false) {
                    $expression = trim(substr($template, $i + 2, $end - ($i + 2)));
                    $result .= json_encode('{{' . $expression . '}}', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $i = $end + 1;
                    $escaped = false;
                    continue;
                }
            }

            $result .= $char;
            $escaped = $char === '\\' && !$escaped;

            if ($char !== '\\') {
                $escaped = false;
            }
        }

        return $result;
    }

    private function normalizeGohighlevelPlaceholderField(string $expression, array $allowedContextVariables = []): ?string
    {
        $expression = trim($expression);

        foreach ($allowedContextVariables as $variable) {
            if (strcasecmp($expression, (string) $variable) === 0) {
                return (string) $variable;
            }
        }

        if (!preg_match('/^\$?lead\s*(?:->|\.)\s*([A-Za-z_][A-Za-z0-9_]*)\s*$/', $expression, $matches)) {
            return null;
        }

        return $matches[1] !== '' ? $matches[1] : null;
    }

    private function normalizeFreshworksPlaceholderPath(string $expression): ?string
    {
        $expression = trim($expression);

        if (!preg_match('/^\$?lead(?:(?:->|\.)[A-Za-z_][A-Za-z0-9_]*)+$/', $expression)) {
            return null;
        }

        $path = preg_replace('/^\$?lead(?:->|\.)/', '', $expression);
        $path = str_replace('->', '.', (string) $path);
        $path = trim((string) $path, '.');

        if (str_starts_with($path, 'campaign_origin.')) {
            $path = 'campaignOrigin.' . substr($path, strlen('campaign_origin.'));
        }

        return $path !== '' ? $path : null;
    }

    private function clearSalesforceFields(array $payload): array
    {
        $payload['url_credenciales'] = null;
        $payload['username'] = null;
        $payload['password'] = null;
        $payload['body'] = null;

        return $payload;
    }

    private function clearSalesforceCredentialFields(array $payload): array
    {
        $payload['url_credenciales'] = null;
        $payload['username'] = null;
        $payload['password'] = null;

        return $payload;
    }

    private function clearHubspotFields(array $payload): array
    {
        $payload['body_oportunidad'] = null;
        $payload['url_consulta_lead'] = null;
        $payload['url_negocio'] = null;
        $payload['url_creacionlead'] = null;
        $payload['dealname'] = null;
        $payload['dealstage'] = null;

        return $payload;
    }

    private function clearKommoPipelineFields(array $payload): array
    {
        $payload['kommo_pipeline_default_pipeline_id'] = null;
        $payload['kommo_pipeline_default_pipeline_name'] = null;
        $payload['kommo_pipeline_default_status_id'] = null;
        $payload['kommo_pipeline_default_status_name'] = null;

        return $payload;
    }

    private function syncKommoPipelineConditions(Integration $integration, array $validated): void
    {
        $typeName = $this->normalizeIntegrationTypeName(optional($integration->integrationtype)->name);

        if ($typeName === '') {
            $typeName = $this->normalizeIntegrationTypeName(
                Integrationtype::whereKey($integration->integrationtype_id)->value('name')
            );
        }

        if ($typeName !== 'kommopipeline') {
            $integration->kommoPipelineConditions()->delete();
            return;
        }

        $conditions = collect($validated['kommo_pipeline_conditions'] ?? [])
            ->filter(fn ($condition) => filled($condition['lead_field'] ?? null))
            ->values();

        $integration->kommoPipelineConditions()->delete();

        foreach ($conditions as $index => $condition) {
            $integration->kommoPipelineConditions()->create([
                'lead_field' => $condition['lead_field'],
                'expected_value' => $condition['expected_value'],
                'pipeline_id' => $condition['pipeline_id'],
                'pipeline_name' => $condition['pipeline_name'] ?? null,
                'status_id' => $condition['status_id'],
                'status_name' => $condition['status_name'] ?? null,
                'order' => $condition['order'] ?? $index,
                'active' => (bool) ($condition['active'] ?? true),
            ]);
        }
    }

    private function syncAtomConfiguration(Integration $integration, array $validated): void
    {
        $typeName = $this->normalizeIntegrationTypeName(optional($integration->integrationtype)->name);

        if ($typeName === '') {
            $typeName = $this->normalizeIntegrationTypeName(
                Integrationtype::whereKey($integration->integrationtype_id)->value('name')
            );
        }

        if ($typeName !== 'atom') {
            $integration->atomConditions()->delete();
            $integration->atomWebhooks()->delete();
            return;
        }

        $webhooks = collect($validated['atom_webhooks'] ?? [])
            ->filter(fn ($webhook) => filled($webhook['key'] ?? null))
            ->values();
        $conditions = collect($validated['atom_conditions'] ?? [])
            ->filter(fn ($condition) => filled($condition['lead_field'] ?? null))
            ->values();

        $integration->atomConditions()->delete();
        $integration->atomWebhooks()->delete();

        $webhookMap = [];

        foreach ($webhooks as $index => $webhook) {
            $created = $integration->atomWebhooks()->create([
                'name' => $webhook['name'],
                'url' => $webhook['url'],
                'order' => $webhook['order'] ?? $index,
                'active' => (bool) ($webhook['active'] ?? true),
                'is_default' => (bool) ($webhook['is_default'] ?? false),
            ]);

            $webhookMap[(string) $webhook['key']] = $created->id;
        }

        foreach ($conditions as $index => $condition) {
            $webhookId = $webhookMap[(string) ($condition['webhook_key'] ?? '')] ?? null;

            if ($webhookId === null) {
                continue;
            }

            $integration->atomConditions()->create([
                'atom_webhook_id' => $webhookId,
                'lead_field' => $condition['lead_field'],
                'expected_value' => $condition['expected_value'],
                'order' => $condition['order'] ?? $index,
                'active' => (bool) ($condition['active'] ?? true),
            ]);
        }
    }

    private function syncLetyConfiguration(Integration $integration, array $validated): void
    {
        $typeName = $this->normalizeIntegrationTypeName(optional($integration->integrationtype)->name);

        if ($typeName === '') {
            $typeName = $this->normalizeIntegrationTypeName(
                Integrationtype::whereKey($integration->integrationtype_id)->value('name')
            );
        }

        if ($typeName !== 'lety') {
            $integration->letyConditions()->delete();
            $integration->letyWebhooks()->delete();
            return;
        }

        $webhooks = collect($validated['lety_webhooks'] ?? [])
            ->filter(fn ($webhook) => filled($webhook['key'] ?? null))
            ->values();
        $conditions = collect($validated['lety_conditions'] ?? [])
            ->filter(fn ($condition) => filled($condition['lead_field'] ?? null))
            ->values();

        $integration->letyConditions()->delete();
        $integration->letyWebhooks()->delete();

        $webhookMap = [];

        foreach ($webhooks as $index => $webhook) {
            $created = $integration->letyWebhooks()->create([
                'name' => $webhook['name'],
                'url' => $webhook['url'],
                'body' => $webhook['body'],
                'order' => $webhook['order'] ?? $index,
                'active' => (bool) ($webhook['active'] ?? true),
            ]);

            $webhookMap[(string) $webhook['key']] = $created->id;
        }

        foreach ($conditions as $index => $condition) {
            $webhookId = $webhookMap[(string) ($condition['webhook_key'] ?? '')] ?? null;

            if ($webhookId === null) {
                continue;
            }

            $integration->letyConditions()->create([
                'lety_webhook_id' => $webhookId,
                'lead_field' => $condition['lead_field'],
                'expected_value' => $condition['expected_value'],
                'order' => $condition['order'] ?? $index,
                'active' => (bool) ($condition['active'] ?? true),
            ]);
        }
    }

    private function syncFreshworksVariableMappings(Integration $integration, array $validated): void
    {
        $typeName = $this->normalizeIntegrationTypeName(optional($integration->integrationtype)->name);

        if ($typeName === '') {
            $typeName = $this->normalizeIntegrationTypeName(
                Integrationtype::whereKey($integration->integrationtype_id)->value('name')
            );
        }

        if ($typeName !== 'freshworks') {
            $integration->freshworksVariableMappings()->delete();
            return;
        }

        $mappings = collect($validated['freshworks_variable_mappings'] ?? [])
            ->filter(fn ($mapping) => filled($mapping['target_variable'] ?? null) && filled($mapping['lead_field'] ?? null))
            ->values();

        $integration->freshworksVariableMappings()->delete();

        foreach ($mappings as $index => $mapping) {
            $integration->freshworksVariableMappings()->create([
                'target_variable' => trim((string) $mapping['target_variable']),
                'lead_field' => $mapping['lead_field'],
                'expected_value' => $mapping['expected_value'],
                'mapped_value' => array_key_exists('mapped_value', $mapping) && $mapping['mapped_value'] !== ''
                    ? $mapping['mapped_value']
                    : null,
                'order' => $mapping['order'] ?? $index,
                'active' => (bool) ($mapping['active'] ?? true),
            ]);
        }
    }

    private function syncIntegrationVariableMappings(Integration $integration, array $validated): void
    {
        $typeName = $this->normalizeIntegrationTypeName(optional($integration->integrationtype)->name);

        if ($typeName === '') {
            $typeName = $this->normalizeIntegrationTypeName(
                Integrationtype::whereKey($integration->integrationtype_id)->value('name')
            );
        }

        if (!in_array($typeName, self::VARIABLE_MAPPING_INTEGRATION_TYPES, true)) {
            $integration->variableMappings()->delete();
            return;
        }

        $mappings = collect($validated['integration_variable_mappings'] ?? [])
            ->filter(fn ($mapping) => filled($mapping['target_variable'] ?? null) && filled($mapping['lead_field'] ?? null))
            ->values();

        $integration->variableMappings()->delete();

        foreach ($mappings as $index => $mapping) {
            $integration->variableMappings()->create([
                'target_variable' => trim((string) $mapping['target_variable']),
                'lead_field' => $mapping['lead_field'],
                'expected_value' => $mapping['expected_value'],
                'mapped_value' => array_key_exists('mapped_value', $mapping) && $mapping['mapped_value'] !== ''
                    ? $mapping['mapped_value']
                    : null,
                'order' => $mapping['order'] ?? $index,
                'active' => (bool) ($mapping['active'] ?? true),
            ]);
        }
    }

    private function kommoPipelineRequest(Integration $integration, string $path)
    {
        $url = rtrim((string) $integration->url, '/');
        $token = trim((string) $integration->tokent);

        if (stripos($token, 'bearer ') === 0) {
            $token = trim(substr($token, 7));
        }

        if ($url === '' || $token === '') {
            abort(422, 'La integracion KommoPipeline requiere URL y token guardados.');
        }

        $response = Http::acceptJson()
            ->withToken($token)
            ->get($url . $path);

        if (!$response->successful()) {
            abort($response->status(), $response->body());
        }

        return $response;
    }

    private function normalizeKommoPipelines($json): array
    {
        $pipelines = data_get($json, '_embedded.pipelines', []);

        return collect(is_array($pipelines) ? $pipelines : [])
            ->map(fn ($pipeline) => [
                'id' => (string) data_get($pipeline, 'id'),
                'name' => (string) data_get($pipeline, 'name'),
            ])
            ->filter(fn ($pipeline) => $pipeline['id'] !== '')
            ->values()
            ->all();
    }

    private function normalizeKommoStatuses($json): array
    {
        $statuses = data_get($json, '_embedded.statuses', []);

        return collect(is_array($statuses) ? $statuses : [])
            ->map(fn ($status) => [
                'id' => (string) data_get($status, 'id'),
                'name' => (string) data_get($status, 'name'),
            ])
            ->filter(fn ($status) => $status['id'] !== '')
            ->values()
            ->all();
    }

    private function leadFields(): array
    {
        return Schema::hasTable('leads')
            ? Schema::getColumnListing('leads')
            : [];
    }

    private function generatePublicKey(): string
    {
        do {
            $key = 'pk_' . Str::random(32);
        } while (Integration::where('public_key', $key)->exists());

        return $key;
    }
}
