<?php

namespace App\Http\Controllers;

use App\Http\Services\LeadDashboardMetricsService;
use App\Models\Customer;
use Illuminate\Http\Request;

class DashboardLeadsController extends Controller
{
    public function __construct(private LeadDashboardMetricsService $metrics) {}

    public function leads(Request $request)
    {
        $customerId = $request->integer('customer_id') ?: null;
        $integrationId = $request->integer('integration_id') ?: null;

        // Filtros soportados (Fuente/Medio + extras)
        $filters = $request->only([
            'campaign_origin', // Fuente
            'plataforma',      // Medio
            'lenguaje',
            'geo',
            'crm_state',       // soporta __NULL__ y __NOT_NULL__
            'qualification',
        ]);

        $customers = Customer::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $selectedCustomer = $customerId
            ? $customers->firstWhere('id', $customerId)
            : null;

        $sessionId = $request->session()->getId();

        $metric = $this->metrics->getLeadsLast7DaysCount(
            $customerId,
            $integrationId,
            $sessionId,
            $filters
        );

        return view('dashboard.leads', compact(
            'customers',
            'customerId',
            'integrationId',
            'selectedCustomer',
            'metric'
        ));
    }

    public function leadsList(Request $request)
    {
        $customerId = $request->integer('customer_id') ?: null;
        $integrationId = $request->integer('integration_id') ?: null;

        $filters = $request->only([
            'campaign_origin',
            'plataforma',
            'lenguaje',
            'geo',
            'crm_state',       // __NULL__ | __NOT_NULL__ | ID real
            'qualification',
        ]);

        $perPage = max(10, min(100, (int) ($request->integer('per_page') ?: 25)));

        $leads = $this->metrics->getLeadsLast7DaysList(
            $customerId,
            $integrationId,
            $filters,
            $perPage
        );

        $customers = Customer::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $selectedCustomer = $customerId
            ? $customers->firstWhere('id', $customerId)
            : null;

        return view('dashboard.leads_list', compact(
            'leads',
            'customers',
            'customerId',
            'integrationId',
            'selectedCustomer',
            'filters'
        ));
    }
}
