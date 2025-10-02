<?php

namespace App\Http\Controllers;

use App\Models\integrationtype;
use Illuminate\Http\Request;

class IntegrationtypeController extends Controller
{
    // Listar todos los tipos de integración
    public function index(Request $request)
    {
        $customerId = $request->header('X-Customer-ID');

        if (!$customerId) {
            return response()->json(['message' => 'Missing X-Customer-ID header'], 400);
        }

        if ($customerId == 1) {
            // Admin: devolver todos
            $types = Integrationtype::all();
        } else {
            // Cliente normal: devolver solo sus tipos de integración
            $types = Integrationtype::where('customer_id', $customerId)->get();
        }

        return response()->json($types);
    }


    // Crear un nuevo tipo de integración
    public function store(Request $request)
    {
        $customerId = $request->header('X-Customer-ID');

        if (!$customerId) {
            return response()->json(['message' => 'Missing X-Customer-ID header'], 400);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|boolean',
        ]);

        $type = Integrationtype::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
            'customer_id' => $customerId, // <<< Asignar automáticamente el customer_id
        ]);

        return response()->json([
            'message' => 'Integration Type created successfully',
            'data' => $type,
        ], 201);
    }

    // Mostrar un tipo de integración específico
    public function show(Request $request, $id)
    {
        $customerId = $request->header('X-Customer-ID');

        if (!$customerId) {
            return response()->json(['message' => 'Missing X-Customer-ID header'], 400);
        }

        $type = Integrationtype::find($id);

        if (!$type) {
            return response()->json(['message' => 'Integration Type not found'], 404);
        }

        if ($customerId != 1 && $type->customer_id != $customerId) {
            return response()->json(['message' => 'Unauthorized to view this Integration Type'], 403);
        }

        return response()->json($type);
    }


    // Actualizar un tipo de integración
    public function update(Request $request, $id)
    {
        $customerId = $request->header('X-Customer-ID');

        if (!$customerId) {
            return response()->json(['message' => 'Missing X-Customer-ID header'], 400);
        }

        $type = Integrationtype::find($id);

        if (!$type) {
            return response()->json(['message' => 'Integration Type not found'], 404);
        }

        if ($customerId != 1 && $type->customer_id != $customerId) {
            return response()->json(['message' => 'Unauthorized to update this Integration Type'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|boolean',
        ]);

        $type->update($validated);

        return response()->json([
            'message' => 'Integration Type updated successfully',
            'data' => $type,
        ]);
    }


    // Eliminar un tipo de integración
    public function destroy(Request $request, $id)
    {
        $customerId = $request->header('X-Customer-ID');

        if (!$customerId) {
            return response()->json(['message' => 'Missing X-Customer-ID header'], 400);
        }

        $type = Integrationtype::find($id);

        if (!$type) {
            return response()->json(['message' => 'Integration Type not found'], 404);
        }

        if ($customerId != 1 && $type->customer_id != $customerId) {
            return response()->json(['message' => 'Unauthorized to delete this Integration Type'], 403);
        }

        $type->delete();

        return response()->json(['message' => 'Integration Type deleted successfully']);
    }
}
