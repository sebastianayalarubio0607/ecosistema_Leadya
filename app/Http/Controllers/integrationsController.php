<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    public function index()
    {
        $integrations = Integration::with(['customer', 'integrationType'])->get();
        return response()->json($integrations);
    }

    public function show($id)
    {
        $integration = Integration::with(['customer', 'integrationType'])->findOrFail($id);
        return response()->json($integration);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'integrationtype_id' => 'required|exists:integrationtypes,id',
            'customer_id' => 'required|exists:customers,id',
            'url' => 'required|string',
            'tokent' => 'nullable|string',
            'status' => 'required|boolean',
        ]);

        $integration = Integration::create($validated);

        return response()->json([
            'message' => 'Integration created successfully',
            'data' => $integration,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $integration = Integration::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'integrationtype_id' => 'sometimes|exists:integrationtypes,id',
            'customer_id' => 'sometimes|exists:customers,id',
            'url' => 'sometimes|string',
            'tokent' => 'nullable|string',
            'status' => 'sometimes|boolean',
        ]);

        $integration->update($validated);

        return response()->json([
            'message' => 'Integration updated successfully',
            'data' => $integration,
        ]);
    }

    public function destroy($id)
    {
        $integration = Integration::findOrFail($id);
        $integration->delete();

        return response()->json([
            'message' => 'Integration deleted successfully',
        ]);
    }
}
