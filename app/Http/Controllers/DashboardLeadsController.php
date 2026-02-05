<?php

namespace App\Http\Controllers;

use App\Http\Services\LeadDashboardMetricsService;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class DashboardLeadsController extends Controller
{
    public function __construct(private LeadDashboardMetricsService $metrics) {}

    public function leads(Request $request)
    {
        $customerId     = $request->integer('customer_id') ?: null;
        $integrationId  = $request->integer('integration_id') ?: null;

        $filters = $request->only([
            'campaign_origin',
            'plataforma',
            'lenguaje',
            'geo',
            'crm_state',
            'qualification',
        ]);

        $customers = Customer::query()->orderBy('name')->get(['id', 'name']);
        $selectedCustomer = $customerId ? $customers->firstWhere('id', $customerId) : null;
        

        $metric = $this->metrics->getLeadsLast7DaysCount(
            $customerId,
            $integrationId,
            $request->session()->getId(),
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
        $customerId     = $request->integer('customer_id') ?: null;
        $integrationId  = $request->integer('integration_id') ?: null;

        $groupType = (string) $request->get('group_type', '');
        $groupId   = (string) $request->get('group_id', '');

        if (!in_array($groupType, ['crm_state', 'qualification', 'campaign_origin', 'plataforma', 'funnel'], true)) {
            abort(404);
        }
        if ($groupId === '') {
            abort(404);
        }

        $filters = $request->only([
            'campaign_origin',
            'plataforma',
            'lenguaje',
            'geo',
        ]);

        $customers = Customer::query()->orderBy('name')->get(['id', 'name']);
        $selectedCustomer = $customerId ? $customers->firstWhere('id', $customerId) : null;

        $groupLabel = $this->metrics->resolveGroupLabel($groupType, $groupId);

        $leads = $this->metrics->getLeadsForGroupLast7Days(
            $customerId,
            $integrationId,
            $filters,
            $groupType,
            $groupId,
            20
        )->withQueryString();

        $backUrl = route('dashboard.leads', Arr::except($request->query(), [
            'group_type', 'group_id', 'page'
        ]));

        return view('dashboard.leads_list', compact(
            'customers',
            'customerId',
            'integrationId',
            'selectedCustomer',
            'groupType',
            'groupId',
            'groupLabel',
            'leads',
            'backUrl'
        ));
    }

    /**
     * Export CSV (Excel)
     */
    public function leadsListExport(Request $request)
    {
        $customerId     = $request->integer('customer_id') ?: null;
        $integrationId  = $request->integer('integration_id') ?: null;

        $groupType = (string) $request->get('group_type', '');
        $groupId   = (string) $request->get('group_id', '');

        if (!in_array($groupType, ['crm_state', 'qualification', 'campaign_origin', 'plataforma', 'funnel'], true)) {
            abort(404);
        }
        if ($groupId === '') {
            abort(404);
        }

        $filters = $request->only([
            'campaign_origin',
            'plataforma',
            'lenguaje',
            'geo',
        ]);

        $groupLabel = $this->metrics->resolveGroupLabel($groupType, $groupId);
        $safeLabel = Str::slug($groupLabel ?: 'leads', '_');
        $filename = "leads_{$safeLabel}_" . now()->format('Ymd_His') . ".csv";

        $query = $this->metrics->getLeadsForGroupLast7DaysQuery(
            $customerId,
            $integrationId,
            $filters,
            $groupType,
            $groupId
        );

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 para Excel

            fputcsv($out, ['Fecha','ID','Teléfono','Nombre','Apellido','Fuente','Medio','Estado','Cualificación']);

            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $lead) {
                    $phone = $lead->telefono ?? $lead->phone ?? $lead->phone_number ?? $lead->celular ?? $lead->movil ?? null;
                    $first = $lead->nombre ?? $lead->first_name ?? $lead->name ?? $lead->nombres ?? null;
                    $last  = $lead->apellido ?? $lead->last_name ?? $lead->lastname ?? $lead->apellidos ?? null;

                    $fuente = $lead->campaign_origin;
                    $medio  = $lead->plataforma;

                    $fuenteLabel = ($fuente === null || $fuente === '') ? 'Sin Fuente' : $fuente;
                    $medioLabel  = ($medio === null || $medio === '') ? 'Sin Medio' : $medio;

                    fputcsv($out, [
                        optional($lead->created_at)->format('Y-m-d H:i'),
                        $lead->id,
                        $phone ?? '',
                        $first ?? '',
                        $last ?? '',
                        $fuenteLabel,
                        $medioLabel,
                        $lead->crm_state_name ?? 'Sin Estado',
                        $lead->qualification_name ?? 'Sin Cualificación',
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
