<?php

namespace App\Http\Controllers\AiConnectors;

use App\Http\Controllers\Controller;
use App\Http\Services\AiConnectors\AiConnectorCredentialService;
use App\Models\AiConnector;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AiConnectorController extends Controller
{
    public function __construct(private readonly AiConnectorCredentialService $credentials) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $connectors = AiConnector::query()
            ->with('customer:id,name')
            ->when($q !== '', fn ($query) => $query
                ->where('name', 'like', "%{$q}%")
                ->orWhere('client_id', 'like', "%{$q}%"))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('ai_connectors.index', [
            'connectors' => $connectors,
            'q' => $q,
        ]);
    }

    public function create(): View
    {
        return view('ai_connectors.create', $this->formData(new AiConnector([
            'is_active' => true,
            'allowed_tools' => AiConnector::defaultTools(),
            'max_requests_per_minute' => 20,
            'max_requests_per_day' => 1000,
            'min_request_interval_seconds' => 1,
            'max_date_range_days' => 31,
            'cache_ttl_seconds' => 300,
            'access_token_ttl_minutes' => 60,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        [$connector, $secret] = $this->credentials->create(array_merge(
            $this->validated($request),
            ['created_by' => $request->user()?->id]
        ));

        return redirect()
            ->route('ai-connectors.show', $connector)
            ->with('success', 'Conector IA creado correctamente.')
            ->with('revealed_secret', $secret);
    }

    public function show(AiConnector $aiConnector): View
    {
        $aiConnector->load([
            'customer:id,name',
            'queryLogs' => fn ($query) => $query->latest('created_at')->limit(20),
        ]);

        return view('ai_connectors.show', [
            'connector' => $aiConnector,
            'tools' => AiConnector::AVAILABLE_TOOLS,
            'mcpEndpoint' => route('api.ai-connectors.mcp', absolute: true),
            'tokenEndpoint' => route('api.ai-connectors.oauth.token', absolute: true),
            'metadataEndpoint' => route('api.ai-connectors.oauth.protected-resource', absolute: true),
        ]);
    }

    public function edit(AiConnector $aiConnector): View
    {
        return view('ai_connectors.edit', $this->formData($aiConnector));
    }

    public function update(Request $request, AiConnector $aiConnector): RedirectResponse
    {
        $aiConnector->update($this->validated($request));

        return redirect()
            ->route('ai-connectors.show', $aiConnector)
            ->with('success', 'Conector IA actualizado correctamente.');
    }

    public function revealSecret(AiConnector $aiConnector): RedirectResponse
    {
        return redirect()
            ->route('ai-connectors.show', $aiConnector)
            ->with('revealed_secret', $aiConnector->client_secret_encrypted);
    }

    public function rotateSecret(AiConnector $aiConnector): RedirectResponse
    {
        $secret = $this->credentials->rotateSecret($aiConnector);

        return redirect()
            ->route('ai-connectors.show', $aiConnector)
            ->with('success', 'Contraseña restablecida. Los access tokens anteriores fueron revocados.')
            ->with('revealed_secret', $secret);
    }

    private function formData(AiConnector $connector): array
    {
        return [
            'connector' => $connector,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'tools' => AiConnector::AVAILABLE_TOOLS,
            'adTools' => AiConnector::AD_TOOLS,
        ];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'is_active' => ['required', 'boolean'],
            'allow_ad_metrics' => ['required', 'boolean'],
            'allowed_tools' => ['nullable', 'array'],
            'allowed_tools.*' => ['string', Rule::in(array_keys(AiConnector::AVAILABLE_TOOLS))],
            'allowed_origins' => ['nullable', 'string', 'max:4000'],
            'max_requests_per_minute' => ['required', 'integer', 'min:1', 'max:1000'],
            'max_requests_per_day' => ['required', 'integer', 'min:1', 'max:100000'],
            'min_request_interval_seconds' => ['required', 'integer', 'min:0', 'max:300'],
            'max_date_range_days' => ['required', 'integer', 'min:1', 'max:366'],
            'cache_ttl_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'access_token_ttl_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $allowedTools = collect($data['allowed_tools'] ?? AiConnector::defaultTools())
            ->filter(fn ($tool) => array_key_exists($tool, AiConnector::AVAILABLE_TOOLS))
            ->unique()
            ->values()
            ->all();

        if (! (bool) $data['allow_ad_metrics']) {
            $allowedTools = array_values(array_diff($allowedTools, AiConnector::AD_TOOLS));
        }

        $data['allowed_tools'] = $allowedTools ?: AiConnector::defaultTools();
        $data['allowed_origins'] = $this->parseOrigins((string) Arr::pull($data, 'allowed_origins', ''));

        return $data;
    }

    private function parseOrigins(string $origins): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $origins) ?: [])
            ->map(fn ($origin) => trim($origin))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
