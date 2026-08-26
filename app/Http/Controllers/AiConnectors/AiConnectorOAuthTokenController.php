<?php

namespace App\Http\Controllers\AiConnectors;

use App\Http\Controllers\Controller;
use App\Http\Services\AiConnectors\AiConnectorAuthenticationService;
use App\Http\Services\AiConnectors\AiConnectorCredentialService;
use App\Http\Services\AiConnectors\AiConnectorOAuthAuthorizationService;
use App\Http\Services\AiConnectors\AiConnectorOAuthResourceService;
use App\Models\AiConnector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiConnectorOAuthTokenController extends Controller
{
    public function __construct(
        private readonly AiConnectorAuthenticationService $auth,
        private readonly AiConnectorCredentialService $credentials,
        private readonly AiConnectorOAuthAuthorizationService $authorizations,
        private readonly AiConnectorOAuthResourceService $resources,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        return match ($request->input('grant_type')) {
            'authorization_code' => $this->authorizationCode($request),
            'refresh_token' => $this->refreshToken($request),
            'client_credentials' => $this->clientCredentials($request),
            default => $this->oauthError('unsupported_grant_type', 'Grant type no soportado.', 400),
        };
    }

    private function authorizationCode(Request $request): JsonResponse
    {
        $connector = $this->connectorFromClientCredentials($request)
            ?: $this->connectorFromPublicPkceClient($request);
        if (! $connector) {
            return $this->oauthError('invalid_client', 'Credenciales invalidas o conector inactivo.', 401);
        }

        $code = (string) $request->input('code');
        $redirectUri = (string) $request->input('redirect_uri');
        $resource = $this->resource($request);

        if ($code === '' || $redirectUri === '') {
            return $this->oauthError('invalid_request', 'code y redirect_uri son requeridos.', 400);
        }

        if (! $this->resourceIsAllowed($resource)) {
            return $this->oauthError('invalid_target', 'El parametro resource no corresponde al servidor MCP de Leadsya.', 400);
        }

        $authorizationCode = $this->authorizations->consumeCode(
            connector: $connector,
            code: $code,
            redirectUri: $redirectUri,
            resource: $resource,
            codeVerifier: $request->input('code_verifier'),
        );

        if (! $authorizationCode) {
            return $this->oauthError('invalid_grant', 'Codigo OAuth invalido, expirado o ya usado.', 400);
        }

        return $this->tokenResponse($this->credentials->issueAccessToken(
            $connector,
            $authorizationCode->scopes ?: [AiConnectorCredentialService::READ_SCOPE],
            $resource,
        ));
    }

    private function refreshToken(Request $request): JsonResponse
    {
        $refreshToken = (string) $request->input('refresh_token');
        if ($refreshToken === '') {
            return $this->oauthError('invalid_request', 'refresh_token es requerido.', 400);
        }

        $connector = $this->connectorFromClientCredentials($request)
            ?: $this->connectorFromRefreshTokenClient($request);
        if (! $connector) {
            return $this->oauthError('invalid_client', 'Credenciales invalidas o conector inactivo.', 401);
        }

        $payload = $this->credentials->refreshAccessToken($refreshToken, $connector);
        if (! $payload) {
            return $this->oauthError('invalid_grant', 'Refresh token invalido, expirado o ya usado.', 400);
        }

        return $this->tokenResponse($payload);
    }

    private function clientCredentials(Request $request): JsonResponse
    {
        $connector = $this->connectorFromClientCredentials($request);
        if (! $connector) {
            return $this->oauthError('invalid_client', 'Credenciales invalidas o conector inactivo.', 401);
        }

        try {
            $scopes = $this->credentials->validScopes($request->input('scope'));
        } catch (\InvalidArgumentException $exception) {
            return $this->oauthError('invalid_scope', $exception->getMessage(), 400);
        }

        $resource = $this->resource($request);
        if (! $this->resourceIsAllowed($resource)) {
            return $this->oauthError('invalid_target', 'El parametro resource no corresponde al servidor MCP de Leadsya.', 400);
        }

        return $this->tokenResponse($this->credentials->issueAccessToken($connector, $scopes, $resource));
    }

    private function connectorFromClientCredentials(Request $request)
    {
        $clientId = (string) ($request->input('client_id') ?: $request->getUser());
        $clientSecret = (string) ($request->input('client_secret') ?: $request->getPassword());

        if ($clientId === '' || $clientSecret === '') {
            return null;
        }

        return $this->auth->connectorForClientCredentials($clientId, $clientSecret);
    }

    private function tokenResponse(array $payload): JsonResponse
    {
        return response()->json($payload)
            ->header('Cache-Control', 'no-store')
            ->header('Pragma', 'no-cache');
    }

    private function resource(Request $request): string
    {
        return $this->resources->canonicalize((string) ($request->input('resource') ?: $this->resources->mcpResource()));
    }

    private function resourceIsAllowed(string $resource): bool
    {
        return $this->resources->isMcpResource($resource);
    }

    private function connectorFromPublicPkceClient(Request $request): ?AiConnector
    {
        $clientId = (string) $request->input('client_id');

        if ($clientId === '' || blank($request->input('code_verifier'))) {
            return null;
        }

        return AiConnector::query()
            ->where('client_id', $clientId)
            ->where('is_active', true)
            ->first();
    }

    private function connectorFromRefreshTokenClient(Request $request): ?AiConnector
    {
        $clientId = (string) $request->input('client_id');

        if ($clientId === '' || blank($request->input('refresh_token'))) {
            return null;
        }

        return AiConnector::query()
            ->where('client_id', $clientId)
            ->where('is_active', true)
            ->first();
    }

    private function oauthError(string $error, string $description, int $status): JsonResponse
    {
        return response()->json([
            'error' => $error,
            'error_description' => $description,
        ], $status)->withHeaders([
            'Cache-Control' => 'no-store',
            'Pragma' => 'no-cache',
        ]);
    }
}
