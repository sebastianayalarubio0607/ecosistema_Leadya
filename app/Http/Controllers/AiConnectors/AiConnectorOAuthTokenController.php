<?php

namespace App\Http\Controllers\AiConnectors;

use App\Http\Controllers\Controller;
use App\Http\Services\AiConnectors\AiConnectorAuthenticationService;
use App\Http\Services\AiConnectors\AiConnectorCredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiConnectorOAuthTokenController extends Controller
{
    public function __construct(
        private readonly AiConnectorAuthenticationService $auth,
        private readonly AiConnectorCredentialService $credentials,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        if ($request->input('grant_type') !== 'client_credentials') {
            return $this->oauthError('unsupported_grant_type', 'Solo se soporta client_credentials.', 400);
        }

        $clientId = (string) ($request->input('client_id') ?: $request->getUser());
        $clientSecret = (string) ($request->input('client_secret') ?: $request->getPassword());

        if ($clientId === '' || $clientSecret === '') {
            return $this->oauthError('invalid_client', 'client_id y client_secret son requeridos.', 401);
        }

        $connector = $this->auth->connectorForClientCredentials($clientId, $clientSecret);
        if (! $connector) {
            return $this->oauthError('invalid_client', 'Credenciales invalidas o conector inactivo.', 401);
        }

        try {
            $scopes = $this->credentials->validScopes($request->input('scope'));
        } catch (\InvalidArgumentException $exception) {
            return $this->oauthError('invalid_scope', $exception->getMessage(), 400);
        }

        return response()->json($this->credentials->issueAccessToken($connector, $scopes))
            ->header('Cache-Control', 'no-store')
            ->header('Pragma', 'no-cache');
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
