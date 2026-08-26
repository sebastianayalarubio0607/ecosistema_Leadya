<?php

namespace App\Http\Controllers\AiConnectors;

use App\Http\Controllers\Controller;
use App\Http\Services\AiConnectors\AiConnectorCredentialService;
use App\Http\Services\AiConnectors\AiConnectorOAuthResourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiConnectorOAuthMetadataController extends Controller
{
    public function __construct(private readonly AiConnectorOAuthResourceService $resources) {}

    public function protectedResource(Request $request): JsonResponse
    {
        return response()->json([
            'resource' => $this->resources->mcpResource(),
            'authorization_servers' => [
                $this->resources->issuer($request),
            ],
            'scopes_supported' => [
                AiConnectorCredentialService::READ_SCOPE,
            ],
            'bearer_methods_supported' => ['header'],
        ]);
    }

    public function authorizationServer(Request $request): JsonResponse
    {
        return response()->json([
            'issuer' => $this->resources->issuer($request),
            'authorization_endpoint' => route('ai-connectors.oauth.authorize.public', absolute: true),
            'token_endpoint' => route('ai-connectors.oauth.token', absolute: true),
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_methods_supported' => [
                'client_secret_post',
                'client_secret_basic',
            ],
            'scopes_supported' => [
                AiConnectorCredentialService::READ_SCOPE,
            ],
            'response_types_supported' => ['code'],
            'code_challenge_methods_supported' => ['S256', 'plain'],
        ]);
    }
}
