<?php

namespace App\Http\Controllers\AiConnectors;

use App\Http\Controllers\Controller;
use App\Http\Services\AiConnectors\AiConnectorCredentialService;
use App\Http\Services\AiConnectors\AiConnectorOAuthAuthorizationService;
use App\Http\Services\AiConnectors\AiConnectorOAuthResourceService;
use App\Models\AiConnector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AiConnectorOAuthAuthorizeController extends Controller
{
    public function __construct(
        private readonly AiConnectorCredentialService $credentials,
        private readonly AiConnectorOAuthAuthorizationService $authorizations,
        private readonly AiConnectorOAuthResourceService $resources,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $validation = $this->validateAuthorizationRequest($request);
        if ($validation instanceof RedirectResponse) {
            return $validation;
        }

        return view('ai_connectors.oauth_authorize', $validation);
    }

    public function approve(Request $request): RedirectResponse
    {
        $validation = $this->validateAuthorizationRequest($request);
        if ($validation instanceof RedirectResponse) {
            return $validation;
        }

        $code = $this->authorizations->createCode(
            connector: $validation['connector'],
            user: $request->user(),
            redirectUri: $validation['validated']['redirect_uri'],
            resource: $validation['resource'],
            scopes: $validation['scopes'],
            codeChallenge: $validation['validated']['code_challenge'] ?? null,
            codeChallengeMethod: $validation['validated']['code_challenge_method'] ?? null,
        );

        return redirect()->away($this->redirectUri(
            $validation['validated']['redirect_uri'],
            [
                'code' => $code,
                'state' => $validation['validated']['state'] ?? null,
            ]
        ));
    }

    public function deny(Request $request): RedirectResponse
    {
        $redirectUri = (string) $request->input('redirect_uri');
        if ($redirectUri === '') {
            return redirect()->route('ai-connectors.index')->withErrors(['oauth' => 'Solicitud OAuth invalida.']);
        }

        return redirect()->away($this->redirectUri($redirectUri, [
            'error' => 'access_denied',
            'error_description' => 'El usuario no autorizo el conector IA.',
            'state' => $request->input('state'),
        ]));
    }

    private function validateAuthorizationRequest(Request $request): array|RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'response_type' => ['required', Rule::in(['code'])],
            'client_id' => ['required', 'string', 'max:120'],
            'redirect_uri' => ['required', 'url', 'max:2000'],
            'resource' => ['nullable', 'url', 'max:2000'],
            'scope' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:1000'],
            'code_challenge' => ['nullable', 'string', 'max:191'],
            'code_challenge_method' => ['nullable', Rule::in(['plain', 'S256'])],
        ]);

        if ($validator->fails()) {
            return redirect()->route('ai-connectors.index')->withErrors($validator);
        }

        $validated = $validator->validated();
        $resource = $validated['resource'] ?? $this->resources->mcpResource();

        if (! $this->redirectUriIsAllowed($validated['redirect_uri'])) {
            return redirect()->route('ai-connectors.index')->withErrors(['redirect_uri' => 'Redirect URI no autorizada para conectores IA.']);
        }

        if (! $this->resources->isMcpResource($resource)) {
            return redirect()->away($this->redirectUri($validated['redirect_uri'], [
                'error' => 'invalid_target',
                'error_description' => 'El parametro resource no corresponde al servidor MCP de Leadsya.',
                'state' => $validated['state'] ?? null,
            ]));
        }

        $connector = AiConnector::query()
            ->where('client_id', $validated['client_id'])
            ->first();

        if (! $connector || ! $connector->is_active) {
            return redirect()->route('ai-connectors.index')->withErrors(['client_id' => 'Conector IA invalido o inactivo.']);
        }

        try {
            $scopes = $this->credentials->validScopes($validated['scope'] ?? null);
        } catch (\InvalidArgumentException $exception) {
            return redirect()->away($this->redirectUri($validated['redirect_uri'], [
                'error' => 'invalid_scope',
                'error_description' => $exception->getMessage(),
                'state' => $validated['state'] ?? null,
            ]));
        }

        return [
            'connector' => $connector,
            'validated' => $validated,
            'resource' => $this->resources->canonicalize($resource),
            'scopes' => $scopes,
            'query' => Arr::only($validated, [
                'response_type',
                'client_id',
                'redirect_uri',
                'resource',
                'scope',
                'state',
                'code_challenge',
                'code_challenge_method',
            ]),
        ];
    }

    private function redirectUri(string $redirectUri, array $params): string
    {
        $params = array_filter($params, fn ($value) => $value !== null && $value !== '');
        $separator = str_contains($redirectUri, '?') ? '&' : '?';

        return $redirectUri.($params === [] ? '' : $separator.http_build_query($params));
    }

    private function redirectUriIsAllowed(string $redirectUri): bool
    {
        $parts = parse_url($redirectUri);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $path = (string) ($parts['path'] ?? '');

        if ($scheme === 'https' && in_array($host, ['claude.ai', 'claude.com'], true) && $path === '/api/mcp/auth_callback') {
            return true;
        }

        return $scheme === 'http'
            && in_array($host, ['localhost', '127.0.0.1'], true)
            && $path === '/callback';
    }
}
