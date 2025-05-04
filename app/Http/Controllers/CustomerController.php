<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\customer;

class CustomerController extends Controller
{
    // Obtener todos los clientes
    public function index(Request $request)
    {
        $customerId = $request->header('X-Customer-ID');

        if (!$customerId) {
            return response()->json(['message' => 'Missing X-Customer-ID header'], 400);
        }

        if ($customerId == 1) {
            // Admin: Devolver todos los customers
            $customers = Customer::all();
        } else {
            // Cliente normal: solo su propio registro
            $customers = Customer::where('id', $customerId)->get();
        }

        return response()->json($customers);
    }

    // Mostrar un cliente específico
    public function show(Request $request, $id)
    {
        $customerId = $request->header('X-Customer-ID');

        if (!$customerId) {
            return response()->json(['message' => 'Missing X-Customer-ID header'], 400);
        }

        if ($customerId == 1) {
            // Admin puede ver cualquier customer
            $customer = Customer::find($id);
        } else {
            // Cliente solo puede ver su propio perfil
            $customer = Customer::where('id', $customerId)->where('id', $id)->first();
        }

        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        return response()->json($customer);
    }

    // Crear un nuevo cliente
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|unique:customers|max:255',
            'description' => 'nullable|string',
            'status' => 'required|boolean',
        ]);
    
        $plainToken = bin2hex(random_bytes(32));
    
        $customer = new Customer();
        $customer->name = $validatedData['name'];
        $customer->description = $validatedData['description'] ?? null;
        $customer->status = $validatedData['status'];
        $customer->token = hash('sha256', $plainToken);
        $customer->save();
    
        return response()->json([
            'message' => 'Customer created successfully',
            'data' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'description' => $customer->description,
                'status' => $customer->status,
                'token' => $plainToken,
            ]
        ], 201);
    }

    // Actualizar un cliente existente
    public function update(Request $request, $id)
    {
        $customerId = $request->header('X-Customer-ID');

        if (!$customerId) {
            return response()->json(['message' => 'Missing X-Customer-ID header'], 400);
        }

        if ($customerId == 1) {
            $customer = Customer::find($id);
        } else {
            $customer = Customer::where('id', $customerId)->where('id', $id)->first();
        }

        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        $validatedData = $request->validate([
            'name' => 'required|unique:customers,name,' . $id . '|max:255',
            'description' => 'nullable|string',
            'status' => 'required|boolean',
        ]);

        $customer->name = $validatedData['name'];
        $customer->description = $validatedData['description'] ?? $customer->description;
        $customer->status = $validatedData['status'];
        $customer->save();

        return response()->json([
            'message' => 'Customer updated successfully',
            'data' => $customer
        ]);
    }

    // Eliminar un cliente
    public function destroy(Request $request, $id)
    {
        $customerId = $request->header('X-Customer-ID');

        if (!$customerId) {
            return response()->json(['message' => 'Missing X-Customer-ID header'], 400);
        }

        if ($customerId == 1) {
            $customer = Customer::find($id);
        } else {
            $customer = Customer::where('id', $customerId)->where('id', $id)->first();
        }

        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        $customer->delete();

        return response()->json(['message' => 'Customer deleted successfully']);
    }

    // Buscar clientes por nombre
    public function search(Request $request)
    {
        $customerId = $request->header('X-Customer-ID');
        $query = $request->query('q');

        if (!$query) {
            return response()->json(['message' => 'Query parameter is required'], 400);
        }

        if ($customerId == 1) {
            // Admin puede buscar en todos
            $customers = Customer::where('name', 'LIKE', '%' . $query . '%')->get();
        } else {
            // Cliente normal solo busca su propio nombre
            $customers = Customer::where('id', $customerId)
                ->where('name', 'LIKE', '%' . $query . '%')
                ->get();
        }

        return response()->json($customers);
    }

    // Regenerar token
    public function regenerateToken($id)
    {
        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json([
                'error' => 'Customer not found'
            ], 404);
        }

        $plainToken = bin2hex(random_bytes(32));
        $customer->token = hash('sha256', $plainToken);
        $customer->save();

        return response()->json([
            'message' => 'Token regenerated successfully',
            'data' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'token' => $plainToken,
            ]
        ], 200);
    }
}
