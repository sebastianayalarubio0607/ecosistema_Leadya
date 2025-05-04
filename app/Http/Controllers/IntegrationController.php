<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    // Listar todas las integraciones
    public function index(Request $request)
    {
        $customerId = $request->header('X-Customer-ID');

        if (!$customerId) {
            return response()->json(['message' => 'Missing X-Customer-ID header'], 400);
        }

        if ($customerId == 1) {
            // Admin: devolver todas
            $integrations = Integration::with('integrationType')->get();
        } else {
            // Cliente normal: sólo sus integraciones
            $integrations = Integration::where('customer_id', $customerId)
                ->with('integrationType')
                ->get();
        }

        return response()->json($integrations);
    }

    // Crear una nueva integración
    public function store(Request $request)
    {

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'integrationtype_id' => 'required|exists:integrationtypes,id',
            'url' => 'required|url',
            'tokent' => 'nullable|string',
            'status' => 'required|boolean'
        ]);
        
        $customerId = $request->header('X-Customer-ID');

        if (!$customerId) {
            return response()->json(['message' => 'Missing X-Customer-ID header'], 400);
        }

        $integration = Integration::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'integrationtype_id' => $validated['integrationtype_id'],
            'customer_id' => $customerId,
            'url' => $validated['url'],
            'tokent' => $validated['tokent'] ?? null,
            'status' => $validated['status'],
        ]);

        return response()->json([
            'message' => 'Integration created successfully',
            'data' => $integration
        ], 201);
    }

    // Mostrar una integración específica
    public function show(Request $request, $id)
    {
        $customerId = $request->header('X-Customer-ID');

        $integration = ($customerId == 1)
            ? Integration::with('integrationType')->find($id) // Admin: ver cualquier integración
            : Integration::where('customer_id', $customerId)->with('integrationType')->find($id);

        if (!$integration) {
            return response()->json(['message' => 'Integration not found'], 404);
        }

        return response()->json($integration);
    }

    // Actualizar una integración existente
    public function update(Request $request, $id)
    {
        $customerId = $request->header('X-Customer-ID');

        $integration = ($customerId == 1)
            ? Integration::find($id)
            : Integration::where('customer_id', $customerId)->find($id);

        if (!$integration) {
            return response()->json(['message' => 'Integration not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'integrationtype_id' => 'required|exists:integrationtypes,id',
            'url' => 'required|url',
            'tokent' => 'nullable|string',
            'status' => 'required|boolean',
        ]);

        $integration->update($validated);

        return response()->json([
            'message' => 'Integration updated successfully',
            'data' => $integration
        ]);
    }

    // Eliminar una integración
    public function destroy(Request $request, $id)
    {
        $customerId = $request->header('X-Customer-ID');

        $integration = ($customerId == 1)
            ? Integration::find($id)
            : Integration::where('customer_id', $customerId)->find($id);

        if (!$integration) {
            return response()->json(['message' => 'Integration not found'], 404);
        }

        $integration->delete();

        return response()->json(['message' => 'Integration deleted successfully']);
    }
}
