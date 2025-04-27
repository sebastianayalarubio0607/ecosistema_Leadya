<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\customer; // Importar el modelo Customer

class CustomerController extends Controller
{
    // Obtener todos los clientes
    public function index()
    {
        
        $customers = customer::all();
        return response()->json($customers);
    }

    // Mostrar un cliente específico
    public function show($id)
    {
        $customer = customer::find($id);

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
    
        // 1. Generar el token en texto plano
        $plainToken = bin2hex(random_bytes(32)); // 64 caracteres seguros
    
        // 2. Crear el customer
        $customer = new Customer();
        $customer->name = $validatedData['name'];
        $customer->description = $validatedData['description'] ?? null;
        $customer->status = $validatedData['status'];
        $customer->token = hash('sha256', $plainToken); // Guardar el token hasheado
        $customer->save();
    
        // 3. Devolver el token plano junto con los datos
        return response()->json([
            'message' => 'Customer created successfully',
            'data' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'description' => $customer->description,
                'status' => $customer->status,
                'token' => $plainToken, // <<<<< este es el token que el cliente debe guardar
            ]
        ], 201);
    }
    

    // Actualizar un cliente existente
    public function update(Request $request, $id)
    {
        $customer = customer::find($id);

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
    public function destroy($id)
    {
        $customer = customer::find($id);

        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        $customer->delete();

        return response()->json(['message' => 'Customer deleted successfully']);
    }

    // Buscar clientes por nombre
    public function search(Request $request)
    {
        $query = $request->query('q');

        if (!$query) {
            return response()->json(['message' => 'Query parameter is required'], 400);
        }

        $customers = customer::where('name', 'LIKE', '%' . $query . '%')->get();

        return response()->json($customers);
    }

    public function regenerateToken($id)
{
    
    $customer = Customer::find($id);

    if (!$customer) {
        return response()->json([
            'error' => 'Customer not found'
        ], 404);
    }

    // 1. Generar nuevo token plano
    $plainToken = bin2hex(random_bytes(32));

    // 2. Guardar el hash del nuevo token
    $customer->token = hash('sha256', $plainToken);
    $customer->save();

    // 3. Devolver el nuevo token
    return response()->json([
        'message' => 'Token regenerated successfully',
        'data' => [
            'id' => $customer->id,
            'name' => $customer->name,
            'token' => $plainToken, // este es el nuevo token que debe usar
        ]
    ], 200);
}
}


