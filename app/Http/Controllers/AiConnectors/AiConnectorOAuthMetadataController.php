<?php

namespace App\Http\Controllers\AiConnectors;

use App\Http\Controllers\Controller;
use App\Http\Services\AiConnectors\AiConnectorCredentialService;
use Illuminate\Http\JsonResponse;

class AiConnectorOAuthMetadataController extends Controller
{
    public function protectedResource(): JsonResponse
    {
        return response()->json([
            'resource' => route('api.ai-connectors.mcp', absolute: true),
            'authorization_servers' => [
                route('api.ai-connectors.oauth.authorization-server', absolute: true),
            ],
            'scopes_supported' => [
                AiConnectorCredentialService::READ_SCOPE,
            ],
            'bearer_methods_supported' => ['header'],
        ]);
    }

    public function authorizationServer(): JsonResponse
    {
        return response()->json([
            'issuer' => route('api.ai-connectors.oauth.authorization-server', absolute: true),
            'token_endpoint' => route('api.ai-connectors.oauth.token', absolute: true),
            'grant_types_supported' => ['client_credentials'],
            'token_endpoint_auth_methods_supported' => [
                'client_secret_post',
                'client_secret_basic',
            ],
            'scopes_supported' => [
                AiConnectorCredentialService::READ_SCOPE,
            ],
            'response_types_supported' => [],
        ]);
    }
}
