<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Customer;

class ApiAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $customerId = $request->header('X-Customer-ID');
        $token = $request->header('X-Auth-Token');

        if (!$customerId || !$token) {
            return response()->json(['error' => 'Unauthorized: Missing credentials'], 401);
        }

        // Buscar el cliente por id y status = 1
        $customer = Customer::where('id', $customerId)
            ->where('status', 1) // <<<<< CORREGIDO aquí: status debe ser 1, no 'active'
            ->first();

        if (!$customer) {
            return response()->json(['error' => 'Unauthorized: Invalid customer'], 401);
        }

        // Validar token con hash seguro
        if (!hash_equals($customer->token, hash('sha256', $token))) {
            return response()->json(['error' => 'Unauthorized: Invalid token'], 401);
        }

        // Inyectar cliente autenticado en la request (opcional)
        $request->merge(['authenticated_customer' => $customer]);

        return $next($request);
    }
}
