<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Customer;

class ApiAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Manejo de solicitudes preflight (OPTIONS)
        if ($request->getMethod() === 'OPTIONS') {
            return response()->json([], 204)
                ->header('Access-Control-Allow-Origin', '*') // Cambia '*' por un dominio específico si es necesario
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, X-Customer-ID, X-Auth-Token, Authorization');
        }

        // Validación de autenticación
        $customerId = $request->header('X-Customer-ID');
        $token = $request->header('X-Auth-Token');

        if (!$customerId || !$token) {
            return response()->json(['error' => 'Unauthorized: Missing credentials'], 401);
        }

        $customer = Customer::where('id', $customerId)->where('status', 1)->first();

        if (!$customer) {
            return response()->json(['error' => 'Unauthorized: Invalid customer'], 401);
        }

        if (!hash_equals($customer->token, hash('sha256', $token))) {
            return response()->json(['error' => 'Unauthorized: Invalid token'], 401);
        }

        $request->merge(['authenticated_customer' => $customer]);

        // Continuar con la solicitud y agregar cabeceras CORS a la respuesta
        $response = $next($request);
        return $response
            ->header('Access-Control-Allow-Origin', '*') // Cambia '*' por un dominio específico si es necesario
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, X-Customer-ID, X-Auth-Token, Authorization');
    }
}