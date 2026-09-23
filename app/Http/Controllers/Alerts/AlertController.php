<?php

namespace App\Http\Controllers\Alerts;

use App\Http\Controllers\Controller;
use App\Services\Alerts\AlertAvailability;
use Illuminate\Support\Facades\Gate;

class AlertController extends Controller
{
    public function index()
    {
        return $this->page('inbox');
    }

    public function rules()
    {
        Gate::authorize('manage-alerts');

        return $this->page('rules');
    }

    public function history()
    {
        Gate::authorize('manage-alerts');

        return $this->page('history');
    }

    private function page(string $section)
    {
        return view('alerts.index', ['section' => $section, 'available' => app(AlertAvailability::class)->ready()]);
    }
}
