<?php

namespace App\Http\Controllers\CrmState;

use App\Http\Controllers\Controller;
use App\Models\CrmState;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\MetaEvent;
use App\Models\Qualification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CrmStateWebController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->get('q');

        $crmstates = CrmState::query()
            ->with([
                'qualificationModel:id,name',
                'metaEvent:id,nombre',
                'googleAdsConversions.customer:id,name,id_Gads',
            ])
            ->when($q, function ($query) use ($q) {
                $query->where('id', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%");
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('crm_states.index', compact('crmstates', 'q'));
    }

    public function create()
    {
        $crmstate = new CrmState();

        $integrations = Integration::with('customer:id,name,id_Gads')
            ->orderByDesc('id')
            ->get(['id', 'name', 'customer_id']);

        $customers = $this->googleAdsCustomers();
        $qualifications = Qualification::orderBy('name')->get(['id', 'name']);
        $metaEvents = MetaEvent::orderBy('nombre')->get(['id', 'nombre', 'estados']);

        return view('crm_states.create', compact('crmstate', 'integrations', 'customers', 'qualifications', 'metaEvents'));
    }

    public function store(Request $request)
    {
        $integration = Integration::query()
            ->whereKey($request->input('integration_id'))
            ->first();

        $crmStatePrefix = $integration?->crmIdPrefix() ?: (string) $request->input('integration_id');
        $externalId = (string) $request->input('external_id');
        $finalId = $crmStatePrefix . '-' . $externalId;

        $data = $request->all();
        $data['id'] = $finalId;

        $validator = Validator::make($data, $this->rules(true), [
            'external_id.regex' => 'El ID ingresado solo puede tener letras, numeros, guion (-) y guion bajo (_).',
        ]);
        $this->validateGoogleAdsConversions($validator, $request);
        $validator->validate();

        $conversionRows = $this->googleAdsConversionRows($request);
        $firstConversion = $conversionRows[0] ?? [];

        $crmstate = CrmState::create([
            'id' => $finalId,
            'name' => $request->string('name'),
            'qualification' => $request->input('qualification'),
            'meta_event_id' => $request->input('meta_event_id'),
            'unmanaged' => $request->boolean('unmanaged'),
            'google_ads_conversion_enabled' => $request->boolean('google_ads_conversion_enabled'),
            'google_ads_conversion_action_id' => $firstConversion['conversion_action_id'] ?? null,
            'google_ads_conversion_action_name' => $firstConversion['conversion_action_name'] ?? null,
            'google_ads_conversion_action_resource_name' => $firstConversion['conversion_action_resource_name'] ?? null,
            'google_ads_conversion_value' => $request->input('google_ads_conversion_value'),
            'google_ads_conversion_currency' => $this->normalizeCurrency($request->input('google_ads_conversion_currency')),
        ]);

        $this->syncGoogleAdsConversions($crmstate, $conversionRows);

        return redirect()
            ->route('crmstates.index')
            ->with('success', 'CRM State creado correctamente.');
    }

    public function show(CrmState $crmstate)
    {
        $crmstate->load([
            'qualificationModel:id,name',
            'metaEvent:id,nombre,estados',
            'googleAdsConversions.customer:id,name,id_Gads',
        ]);

        [$integrationId, $externalId, $integration] = $this->resolveCrmStateParts($crmstate);

        return view('crm_states.show', compact('crmstate', 'integrationId', 'externalId', 'integration'));
    }

    public function edit(CrmState $crmstate)
    {
        $crmstate->load([
            'qualificationModel:id,name',
            'metaEvent:id,nombre,estados',
            'googleAdsConversions.customer:id,name,id_Gads',
        ]);

        [$integrationId, $externalId, $integration] = $this->resolveCrmStateParts($crmstate);

        $customers = $this->googleAdsCustomers();
        $qualifications = Qualification::orderBy('name')->get(['id', 'name']);
        $metaEvents = MetaEvent::orderBy('nombre')->get(['id', 'nombre', 'estados']);

        return view('crm_states.edit', compact(
            'crmstate',
            'integrationId',
            'externalId',
            'integration',
            'customers',
            'qualifications',
            'metaEvents'
        ));
    }

    public function update(Request $request, CrmState $crmstate)
    {
        $validator = Validator::make($request->all(), $this->rules());
        $this->validateGoogleAdsConversions($validator, $request);
        $validated = $validator->validate();

        $conversionRows = $this->googleAdsConversionRows($request);
        $firstConversion = $conversionRows[0] ?? [];

        $validated['unmanaged'] = $request->boolean('unmanaged');
        $validated['google_ads_conversion_enabled'] = $request->boolean('google_ads_conversion_enabled');
        $validated['google_ads_conversion_action_id'] = $firstConversion['conversion_action_id'] ?? null;
        $validated['google_ads_conversion_action_name'] = $firstConversion['conversion_action_name'] ?? null;
        $validated['google_ads_conversion_action_resource_name'] = $firstConversion['conversion_action_resource_name'] ?? null;
        $validated['google_ads_conversion_currency'] = $this->normalizeCurrency($validated['google_ads_conversion_currency'] ?? null);

        $crmstate->update($validated);
        $this->syncGoogleAdsConversions($crmstate, $conversionRows);

        return redirect()
            ->route('crmstates.index')
            ->with('success', 'CRM State actualizado correctamente.');
    }

    public function destroy(CrmState $crmstate)
    {
        if (method_exists($crmstate, 'leads') && $crmstate->leads()->exists()) {
            return back()->with('success', 'No se puede eliminar: hay leads asociados a este estado.');
        }

        $crmstate->delete();

        return redirect()
            ->route('crmstates.index')
            ->with('success', 'CRM State eliminado.');
    }

    private function rules(bool $creating = false): array
    {
        return array_filter([
            'integration_id' => $creating ? ['required', 'exists:integrations,id'] : null,
            'external_id' => $creating ? ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/'] : null,
            'id' => $creating ? ['required', 'string', 'max:255', Rule::unique('crm_state', 'id')] : null,
            'name' => ['required', 'string', 'max:255'],
            'qualification' => ['required', 'exists:qualification,id'],
            'meta_event_id' => ['nullable', 'exists:meta_events,id'],
            'unmanaged' => ['nullable', 'boolean'],
            'google_ads_conversion_enabled' => ['nullable', 'boolean'],
            'google_ads_conversions' => ['nullable', 'array'],
            'google_ads_conversions.*.customer_id' => ['nullable', 'exists:customers,id'],
            'google_ads_conversions.*.conversion_action_id' => ['nullable', 'string', 'max:64'],
            'google_ads_conversions.*.conversion_action_name' => ['nullable', 'string', 'max:255'],
            'google_ads_conversions.*.conversion_action_resource_name' => ['nullable', 'string', 'max:255'],
            'google_ads_conversion_value' => ['nullable', 'numeric', 'min:0'],
            'google_ads_conversion_currency' => ['nullable', 'string', 'size:3'],
        ]);
    }

    private function validateGoogleAdsConversions($validator, Request $request): void
    {
        $validator->after(function ($validator) use ($request) {
            if (! $request->boolean('google_ads_conversion_enabled')) {
                return;
            }

            $rows = $this->googleAdsConversionRows($request);

            if ($rows === []) {
                $validator->errors()->add('google_ads_conversions', 'Debes agregar al menos una conversion de Google Ads.');
                return;
            }

            $customerIds = [];

            foreach ($rows as $index => $row) {
                if (in_array($row['customer_id'], $customerIds, true)) {
                    $validator->errors()->add("google_ads_conversions.{$index}.customer_id", 'No puedes repetir el mismo customer en la matriz.');
                }

                $customerIds[] = $row['customer_id'];
            }
        });
    }

    private function googleAdsConversionRows(Request $request): array
    {
        return collect($request->input('google_ads_conversions', []))
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => [
                'customer_id' => (int) ($row['customer_id'] ?? 0),
                'conversion_action_id' => trim((string) ($row['conversion_action_id'] ?? '')),
                'conversion_action_name' => trim((string) ($row['conversion_action_name'] ?? '')),
                'conversion_action_resource_name' => trim((string) ($row['conversion_action_resource_name'] ?? '')),
            ])
            ->filter(fn (array $row) => $row['customer_id'] > 0
                && $row['conversion_action_id'] !== ''
                && $row['conversion_action_resource_name'] !== '')
            ->values()
            ->all();
    }

    private function syncGoogleAdsConversions(CrmState $crmstate, array $rows): void
    {
        $crmstate->googleAdsConversions()->delete();

        foreach ($rows as $row) {
            $crmstate->googleAdsConversions()->create($row);
        }
    }

    private function googleAdsCustomers()
    {
        return Customer::query()
            ->whereNotNull('id_Gads')
            ->where('id_Gads', '!=', '')
            ->orderBy('name')
            ->get(['id', 'name', 'id_Gads']);
    }

    private function splitId(string $id): array
    {
        $parts = explode('-', $id, 2);
        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    private function resolveCrmStateParts(CrmState $crmstate): array
    {
        $crmStateId = (string) $crmstate->id;
        $matchedIntegration = null;
        $matchedPrefix = '';

        Integration::with('customer:id,name,id_Gads')
            ->get(['id', 'name', 'customer_id', 'disable_integration_id_crm_prefix', 'crm_id_prefix'])
            ->each(function (Integration $integration) use ($crmStateId, &$matchedIntegration, &$matchedPrefix) {
                $prefix = $integration->crmIdPrefix();

                if ($prefix === '' || ! str_starts_with($crmStateId, $prefix . '-')) {
                    return;
                }

                if (strlen($prefix) > strlen($matchedPrefix)) {
                    $matchedIntegration = $integration;
                    $matchedPrefix = $prefix;
                }
            });

        if ($matchedIntegration) {
            return [
                $matchedPrefix,
                substr($crmStateId, strlen($matchedPrefix) + 1),
                $matchedIntegration,
            ];
        }

        [$integrationId, $externalId] = $this->splitId($crmStateId);

        return [
            $integrationId,
            $externalId,
            Integration::with('customer:id,name,id_Gads')->find($integrationId),
        ];
    }

    private function normalizeCurrency(?string $currency): string
    {
        $currency = strtoupper(trim((string) $currency));

        return $currency !== '' ? $currency : 'COP';
    }
}
