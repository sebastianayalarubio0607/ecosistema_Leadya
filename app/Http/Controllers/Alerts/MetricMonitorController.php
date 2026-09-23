<?php

namespace App\Http\Controllers\Alerts;

use App\Http\Controllers\Controller;
use App\Services\Alerts\Metrics\MetricAvailability;
use Illuminate\Support\Facades\Gate;

class MetricMonitorController extends Controller
{
    public function index()
    {
        Gate::authorize('manage-alerts');

        return view('alerts.index', ['section' => 'metrics', 'available' => app(MetricAvailability::class)->ready()]);
    }
}
