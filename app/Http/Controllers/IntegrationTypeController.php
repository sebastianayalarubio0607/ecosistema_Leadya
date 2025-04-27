<?php

namespace App\Http\Controllers;

use App\Models\IntegrationType;
use Illuminate\Http\Request;

class IntegrationTypeController extends Controller
{
    public function index()
    {
        $types = IntegrationType::all();
        return response()->json($types);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|boolean',
        ]);

        $type = IntegrationType::create($validated);

        return response()->json([
            'message' => 'Integration Type created successfully',
            'data' => $type,
        ], 201);
    }
}
