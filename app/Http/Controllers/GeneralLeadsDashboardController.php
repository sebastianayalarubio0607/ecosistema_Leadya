<?php

namespace App\Http\Controllers;

use App\Http\Requests\GeneralLeadsDashboardFilterRequest;
use App\Http\Services\GeneralLeads\GeneralLeadsDashboardService;
use App\Http\Services\GeneralLeads\GeneralLeadsFilters;
use Illuminate\Http\Response;

class GeneralLeadsDashboardController extends Controller
{
    public function __construct(private readonly GeneralLeadsDashboardService $service) {}

    public function __invoke(GeneralLeadsDashboardFilterRequest $request): Response
    {
        return response()
            ->view('dashboard.general-leads.index')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function list(GeneralLeadsDashboardFilterRequest $request): Response
    {
        $filters = GeneralLeadsFilters::fromRequest($request);

        return response()
            ->view('dashboard.general-leads.list', [
                'dashboard' => $this->service->list($request, $filters),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function exportList(GeneralLeadsDashboardFilterRequest $request)
    {
        $filters = GeneralLeadsFilters::fromRequest($request);

        return $this->service->exportList($request, $filters);
    }
}
