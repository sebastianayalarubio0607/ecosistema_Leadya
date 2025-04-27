<?php

namespace App\Http\Controllers;

use App\Models\LeadIntegration;
use Illuminate\Http\Request;

class LeadIntegrationController extends Controller
{
    public function index()
    {
        $leadIntegrations = LeadIntegration::with(['lead', 'integration'])->get();
        return response()->json($leadIntegrations);
    }

    public function show($id)
    {
        $leadIntegration = LeadIntegration::with(['lead', 'integration'])->findOrFail($id);
        return response()->json($leadIntegration);
    }
}

