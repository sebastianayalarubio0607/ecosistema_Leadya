<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\Integrationtype;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IntegrationWebController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->get('q');

        $integrations = Integration::query()
            ->with(['customer:id,name', 'integrationtype:id,name'])
            ->when($q, function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhere('url', 'like', "%{$q}%")
                    ->orWhere('public_key', 'like', "%{$q}%");
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('integrations.index', compact('integrations', 'q'));
    }

    public function create()
    {
        $integration = new Integration();

        $customers = Customer::orderBy('name')->get(['id', 'name']);
        $types = Integrationtype::orderBy('name')->get(['id', 'name']);

        return view('integrations.create', compact('integration', 'customers', 'types'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $payload = $this->normalizePayloadByType($validated);
        $payload = $this->hydrateZohoTokensFromAuthorizationCode($payload);
        $payload = $this->hydrateSalesforceTokenFromCredentials($payload);

        $payload['public_key'] = $this->generatePublicKey();

        $integration = Integration::create($payload);

        return redirect()
            ->route('integrations.show', $integration)
            ->with('success', 'Integracion creada correctamente.');
    }

    public function show(Integration $integration)
    {
        $integration->load(['customer:id,name', 'integrationtype:id,name']);

        return view('integrations.show', compact('integration'));
    }

    public function edit(Integration $integration)
    {
        $customers = Customer::orderBy('name')->get(['id', 'name']);
        $types = Integrationtype::orderBy('name')->get(['id', 'name']);

        return view('integrations.edit', compact('integration', 'customers', 'types'));
    }

    public function update(Request $request, Integration $integration)
    {
        $validated = $request->validate($this->rules(true));
        $payload = $this->normalizePayloadByType($validated);
        $payload = $this->hydrateSalesforceTokenFromCredentials($payload);

        if ($request->boolean('regenerate_public_key')) {
            $payload['public_key'] = $this->generatePublicKey();
        }

        $integration->update($payload);

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

    private function rules(bool $updating = false): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'integrationtype_id' => 'required|exists:integrationtypes,id',
            'customer_id' => 'required|exists:customers,id',
            'url' => 'required|url',
            'status' => 'nullable|boolean',
            'crm_Id_phone' => ['nullable', 'string', 'max:255'],
            'crm_Id_service' => ['nullable', 'string', 'max:255'],
            'crm_Id_fuente' => ['nullable', 'string', 'max:255'],
            'crm_Id_email' => ['nullable', 'string', 'max:255'],
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
        ];

        if ($updating) {
            $rules['regenerate_public_key'] = 'nullable|boolean';
        }

        return $rules;
    }

    private function normalizePayloadByType(array $validated): array
    {
        $validated['status'] = array_key_exists('status', $validated) ? (int) $validated['status'] : 1;

        if (array_key_exists('access_token', $validated)) {
            $validated['tokent'] = $validated['access_token'];
            unset($validated['access_token']);
        }

        $typeName = strtolower((string) Integrationtype::whereKey($validated['integrationtype_id'])->value('name'));

        if ($typeName === 'google_sheets') {
            $validated = $this->clearKommoFields($validated);
            $validated = $this->clearZohoFields($validated);
            $validated = $this->clearFreshworksFields($validated);
            $validated = $this->clearSalesforceFields($validated);
        }

        if ($typeName === 'kommo') {
            $validated = $this->clearZohoFields($validated);
            $validated = $this->clearFreshworksFields($validated);
            $validated = $this->clearSalesforceFields($validated);
        }

        if ($typeName === 'zoho') {
            $validated = $this->clearKommoFields($validated);
            $validated = $this->clearFreshworksFields($validated);
            $validated = $this->clearSalesforceFields($validated);
        }

        if ($typeName === 'freshworks') {
            $validated = $this->clearKommoFields($validated);
            $validated = $this->clearZohoFieldsPreservingToken($validated);
            $validated = $this->clearSalesforceFields($validated);
            $validated = $this->validateFreshworksPayload($validated);
        }

        if ($typeName === 'salesforce') {
            $validated = $this->clearKommoFields($validated);
            $validated = $this->clearZohoFieldsPreservingToken($validated);
            $validated = $this->clearFreshworksFields($validated);
            $validated = $this->validateSalesforcePayload($validated);
        }

        return $validated;
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

        if (!empty($payload['custom_field']) && !is_array(json_decode($payload['custom_field'], true))) {
            $messages['custom_field'] = 'custom_field debe ser un JSON valido.';
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
            'username' => 'Username',
            'password' => 'Password',
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
            ->withBasicAuth((string) $payload['username'], (string) $payload['password'])
            ->post(rtrim((string) $payload['url_credenciales'], '/') . '?grant_type=client_credentials');

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

    private function clearSalesforceFields(array $payload): array
    {
        $payload['url_credenciales'] = null;
        $payload['username'] = null;
        $payload['password'] = null;
        $payload['body'] = null;

        return $payload;
    }

    private function generatePublicKey(): string
    {
        do {
            $key = 'pk_' . Str::random(32);
        } while (Integration::where('public_key', $key)->exists());

        return $key;
    }
}
