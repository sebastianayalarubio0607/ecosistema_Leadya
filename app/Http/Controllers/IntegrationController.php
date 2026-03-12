<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use App\Models\Integrationtype;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class IntegrationController extends Controller
{
    public function index(Request $request)
    {
        $customerId = $request->header('X-Customer-ID');

        if (! $customerId) {
            return response()->json(['message' => 'Missing X-Customer-ID header'], 400);
        }

        if ((int) $customerId === 1) {
            $integrations = Integration::with('integrationtype')->get();
        } else {
            $integrations = Integration::where('customer_id', $customerId)
                ->with('integrationtype')
                ->get();
        }

        return response()->json($integrations);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $customerId = $request->header('X-Customer-ID');
        if (! $customerId) {
            return response()->json(['message' => 'Missing X-Customer-ID header'], 400);
        }

        $validated['customer_id'] = $customerId;

        $typeName = strtolower((string) Integrationtype::whereKey($validated['integrationtype_id'])->value('name'));

        if (
            $typeName === 'zoho' &&
    isset($validated['client_id'], $validated['client_secret'], $validated['code']) &&
    $validated['client_id'] !== null &&
    $validated['client_secret'] !== null &&
    $validated['code'] !== null
        ) {
            $zohoResult = $this->requestZohoTokens($validated);
            if (isset($zohoResult['error'])) {
                return response()->json([
                    'message' => 'Error consultando tokens de Zoho',
                    'zoho_response' => $zohoResult['error'],
                ], 422);
            }

            $validated['tokent'] = $zohoResult['access_token'];
            $validated['refresh_token'] = $zohoResult['refresh_token'] ?? ($validated['refresh_token'] ?? null);
            $validated['scope'] = $zohoResult['scope'] ?? null;
            $validated['api_domain'] = $zohoResult['api_domain'] ?? ($validated['api_domain'] ?? null);
            $validated['token_type'] = $zohoResult['token_type'] ?? null;
            $validated['expires_in'] = isset($zohoResult['expires_in']) ? (int) $zohoResult['expires_in'] : null;
            $validated['token_expires_at'] = isset($zohoResult['expires_in'])
                ? now()->addSeconds((int) $zohoResult['expires_in'])
                : null;
        }

        $integration = Integration::create($validated);

        return response()->json([
            'message' => 'Integration created successfully',
            'data' => $integration,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $customerId = $request->header('X-Customer-ID');

        $integration = ((int) $customerId === 1)
            ? Integration::with('integrationtype')->find($id)
            : Integration::where('customer_id', $customerId)->with('integrationtype')->find($id);

        if (! $integration) {
            return response()->json(['message' => 'Integration not found'], 404);
        }

        return response()->json($integration);
    }

    public function update(Request $request, $id)
    {
        $customerId = $request->header('X-Customer-ID');

        $integration = ((int) $customerId === 1)
            ? Integration::find($id)
            : Integration::where('customer_id', $customerId)->find($id);

        if (! $integration) {
            return response()->json(['message' => 'Integration not found'], 404);
        }

        $validated = $request->validate($this->rules());

        $integration->update($validated);

        return response()->json([
            'message' => 'Integration updated successfully',
            'data' => $integration,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $customerId = $request->header('X-Customer-ID');

        $integration = ((int) $customerId === 1)
            ? Integration::find($id)
            : Integration::where('customer_id', $customerId)->find($id);

        if (! $integration) {
            return response()->json(['message' => 'Integration not found'], 404);
        }

        $integration->delete();

        return response()->json(['message' => 'Integration deleted successfully']);
    }

    private function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'integrationtype_id' => 'required|exists:integrationtypes,id',
            'url' => 'required|url',
            'status' => 'required|boolean',
            'tokent' => 'nullable|string',
            'crm_Id_phone' => ['nullable', 'string', 'max:255'],
            'crm_Id_service' => ['nullable', 'string', 'max:255'],
            'crm_Id_fuente' => ['nullable', 'string', 'max:255'],
            'crm_Id_email' => ['nullable', 'string', 'max:255'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string'],
            'code' => ['nullable', 'string'],
            'refresh_token' => ['nullable', 'string'],
            'api_domain' => ['nullable', 'url', 'max:255'],
            'scope' => ['nullable', 'string'],
            'token_type' => ['nullable', 'string', 'max:30'],
            'expires_in' => ['nullable', 'integer', 'min:1'],
            'token_expires_at' => ['nullable', 'date'],
        ];
    }

    private function requestZohoTokens(array $validated): array
    {
        if (empty($validated['client_id']) || empty($validated['client_secret']) || empty($validated['code'])) {
            return ['error' => 'Para Zoho se requieren client_id, client_secret y code.'];
        }

        $accountsUrl = rtrim((string) $validated['url'], '/');
        $query = [
            'grant_type' => 'authorization_code',
            'client_id' => $validated['client_id'],
            'client_secret' => $validated['client_secret'],
            'code' => $validated['code'],
        ];

        $response = Http::acceptJson()->post($accountsUrl.'/oauth/v2/token?'.http_build_query($query));

        $json = $response->json();

        if (! $response->successful() || ! is_array($json) || isset($json['error']) || empty($json['access_token'])) {
            return ['error' => is_array($json) ? $json : $response->body()];
        }

        return $json;
    }
}
