<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\LeadCrmStateController;
use App\Http\Requests\GeneralLeadsDashboardFilterRequest;
use App\Http\Services\GeneralLeads\GeneralLeadsDashboardService;
use App\Http\Services\GeneralLeads\GeneralLeadsFilters;
use App\Http\Services\GeneralLeads\GeneralLeadsLeadQuery;
use App\Http\Services\Lead\LeadFunnelHistoryService;
use App\Models\CrmState;
use App\Models\Integration;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

class LeadManagementController extends Controller
{
    public function __construct(
        private readonly GeneralLeadsDashboardService $dashboardService,
        private readonly GeneralLeadsLeadQuery $leads,
        private readonly LeadCrmStateController $crmStateController,
        private readonly LeadFunnelHistoryService $historyService,
    ) {
    }

    public function index(GeneralLeadsDashboardFilterRequest $request): Response
    {
        $filters = GeneralLeadsFilters::fromRequest($request);
        $dashboard = $this->dashboardService->shell($filters);
        $dashboard['filters']['clear_url'] = route('lead-management.index');

        return response()
            ->view('lead-management.index', [
                'dashboard' => $dashboard,
                'table' => $this->table($request, $filters),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function updateCrmState(Request $request, Lead $lead): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'crm_state' => ['required', 'string', 'max:255', 'exists:crm_state,id'],
        ]);

        $validator->after(function ($validator) use ($request, $lead): void {
            $allowed = $this->crmStateOptionsForLead($lead)
                ->pluck('id')
                ->contains((string) $request->input('crm_state'));

            if (! $allowed) {
                $validator->errors()->add('crm_state', 'El estado CRM no pertenece al cliente del lead.');
            }
        });

        $validated = $validator->validate();

        $this->crmStateController->changeLeadStateForLead(
            $lead,
            (string) $validated['crm_state'],
            $this->historyService
        );

        $state = CrmState::query()
            ->leftJoin('qualification as q_update', 'q_update.id', '=', 'crm_state.qualification')
            ->where('crm_state.id', $validated['crm_state'])
            ->first(['crm_state.id', 'crm_state.name', 'q_update.name as qualification_name']);

        return response()->json([
            'message' => 'Estado CRM actualizado.',
            'lead_id' => $lead->id,
            'crm_state' => (string) $state?->id,
            'crm_state_name' => (string) ($state?->name ?? $validated['crm_state']),
            'qualification_name' => (string) ($state?->qualification_name ?? 'Sin Calificacion'),
        ]);
    }

    public function updateValue(Request $request, Lead $lead): JsonResponse
    {
        $validated = $request->validate([
            'value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $lead->value = array_key_exists('value', $validated) && $validated['value'] !== null
            ? number_format((float) $validated['value'], 2, '.', '')
            : null;
        $lead->save();

        return response()->json([
            'message' => 'Valor actualizado.',
            'lead_id' => $lead->id,
            'value' => $lead->value,
        ]);
    }

    private function table(Request $request, GeneralLeadsFilters $filters): array
    {
        if (! $this->shouldShowTable($request)) {
            return [
                'show' => false,
                'leads' => null,
                'notice' => null,
                'blocked' => false,
            ];
        }

        if ($this->selectedCustomerWithoutIntegrations($filters)) {
            return [
                'show' => false,
                'leads' => null,
                'notice' => 'Este cliente no puede ser gestionado porque no tiene una integracion asociada.',
                'blocked' => true,
            ];
        }

        $leads = $this->leads->base($filters)
            ->leftJoin('customers as c_management', 'c_management.id', '=', 'leads.customer_id')
            ->leftJoin('crm_state as cs_management', 'cs_management.id', '=', 'leads.crm_state')
            ->leftJoin('qualification as q_management', 'q_management.id', '=', 'cs_management.qualification')
            ->leftJoin('campaign_objectives as co_management', 'co_management.id', '=', 'leads.campaign_objective')
            ->leftJoin('origins as origin_management', 'origin_management.code', '=', 'leads.campaign_origin')
            ->leftJoin('sources as source_management', 'source_management.id', '=', 'origin_management.source_id')
            ->leftJoin('platforms as platform_management', 'platform_management.code', '=', 'leads.plataforma')
            ->select([
                'leads.id',
                'leads.created_at',
                'leads.customer_id',
                'leads.name',
                'leads.last_name',
                'leads.crm_id',
                'leads.crm_state',
                'leads.page_url',
                'leads.value',
            ])
            ->selectRaw("COALESCE(NULLIF(c_management.name, ''), 'Sin Cliente') as customer_name")
            ->selectRaw("COALESCE(NULLIF(cs_management.name, ''), NULLIF(leads.crm_state, ''), 'Sin Estado') as crm_state_name")
            ->selectRaw("COALESCE(NULLIF(q_management.name, ''), 'Sin Calificacion') as qualification_name")
            ->selectRaw("COALESCE(NULLIF(source_management.name, ''), 'Sin Fuente') as source_name")
            ->selectRaw("COALESCE(NULLIF(platform_management.name, ''), NULLIF(leads.plataforma, ''), 'Sin Medio') as medium_name")
            ->selectRaw("COALESCE(NULLIF(co_management.nombre, ''), 'Sin Campaign Objective') as campaign_objective_name")
            ->orderByDesc('leads.created_at')
            ->paginate(25)
            ->withQueryString();

        $this->attachCrmStateOptions($leads->getCollection());

        return [
            'show' => true,
            'leads' => $leads,
            'notice' => null,
            'blocked' => false,
        ];
    }

    private function shouldShowTable(Request $request): bool
    {
        foreach (['customer_id', 'integration_id', 'from', 'to', 'source', 'campaign_origin', 'plataforma', 'crm_state', 'qualification', 'lenguaje', 'geo'] as $key) {
            if ($request->filled($key)) {
                return true;
            }
        }

        return false;
    }

    private function selectedCustomerWithoutIntegrations(GeneralLeadsFilters $filters): bool
    {
        return $filters->customerId !== null
            && ! Integration::query()->where('customer_id', $filters->customerId)->exists();
    }

    private function attachCrmStateOptions(Collection $leads): void
    {
        $optionsByPrefixSet = collect();

        $leads->each(function (Lead $lead) use ($optionsByPrefixSet): void {
            $prefixes = $this->crmStatePrefixesForLead($lead);
            $cacheKey = $prefixes->implode('|');

            if ($cacheKey === '') {
                $lead->setAttribute('crm_state_options', collect());
                return;
            }

            if (! $optionsByPrefixSet->has($cacheKey)) {
                $optionsByPrefixSet->put($cacheKey, $this->crmStateOptionsForPrefixes($prefixes));
            }

            $lead->setAttribute('crm_state_options', $optionsByPrefixSet->get($cacheKey, collect()));
        });
    }

    private function crmStateOptionsForLead(Lead $lead): Collection
    {
        return $this->crmStateOptionsForPrefixes($this->crmStatePrefixesForLead($lead));
    }

    private function crmStateOptionsForPrefixes(Collection $prefixes): Collection
    {
        if ($prefixes->isEmpty()) {
            return collect();
        }

        return CrmState::query()
            ->leftJoin('qualification as q_options', 'q_options.id', '=', 'crm_state.qualification')
            ->where(function ($query) use ($prefixes): void {
                foreach ($prefixes as $prefix) {
                    $query->orWhere('crm_state.id', 'like', $this->crmStatePrefixLike((string) $prefix));
                }
            })
            ->orderBy('crm_state.name')
            ->get(['crm_state.id', 'crm_state.name', 'q_options.name as qualification_name']);
    }

    private function crmStatePrefixesForLead(Lead $lead): Collection
    {
        return $this->integrationCrmStatePrefixesForCustomer((int) $lead->customer_id)
            ->merge([
                $this->prefixFromCrmIdentifier($lead->crm_state),
                $this->prefixFromCrmIdentifier($lead->crm_id),
            ])
            ->filter()
            ->unique()
            ->values();
    }

    private function integrationCrmStatePrefixesForCustomer(int $customerId): Collection
    {
        return Integration::query()
            ->where('customer_id', $customerId)
            ->get(['id', 'disable_integration_id_crm_prefix', 'crm_id_prefix'])
            ->map(fn (Integration $integration) => $integration->crmIdPrefix())
            ->filter()
            ->unique()
            ->values();
    }

    private function prefixFromCrmIdentifier(?string $identifier): ?string
    {
        $identifier = trim((string) $identifier);

        if ($identifier === '' || ! str_contains($identifier, '-')) {
            return null;
        }

        return explode('-', $identifier, 2)[0] ?: null;
    }

    private function crmStatePrefixLike(string $prefix): string
    {
        return addcslashes($prefix, '\\%_') . '-%';
    }
}
