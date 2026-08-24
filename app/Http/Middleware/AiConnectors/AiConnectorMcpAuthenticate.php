<?php

namespace App\Http\Middleware\AiConnectors;

use App\Http\Services\AiConnectors\AiConnectorAuthenticationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AiConnectorMcpAuthenticate
{
    public function __construct(private readonly AiConnectorAuthenticationService $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (! $bearer) {
            return $this->challenge('invalid_token', 'Token Bearer requerido.');
        }

        $accessToken = $this->auth->accessTokenForBearer($bearer);
        if (! $accessToken) {
            return $this->challenge('invalid_token', 'Token invalido, expirado o sin scope suficiente.');
        }

        $request->attributes->set('ai_connector', $accessToken->connector);
        $request->attributes->set('ai_connector_access_token', $accessToken);

        return $next($request);
    }

    private function challenge(string $error, string $description): Response
    {
        return response()->json([
            'error' => $error,
            'error_description' => $description,
        ], 401)->withHeaders([
            'WWW-Authenticate' => sprintf(
                'Bearer error="%s", scope="%s", resource_metadata="%s"',
                $error,
                'general-leads:read',
                route('api.ai-connectors.oauth.protected-resource', absolute: true)
            ),
        ]);
    }
}
