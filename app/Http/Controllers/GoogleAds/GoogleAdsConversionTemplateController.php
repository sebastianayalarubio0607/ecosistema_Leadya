<?php

namespace App\Http\Controllers\GoogleAds;

use App\Http\Controllers\Controller;
use App\Jobs\EnsureGoogleAdsConversionTemplatesJob;
use App\Models\Customer;
use App\Models\GoogleAdsConversionTemplate;
use App\Models\GoogleAdsConversionTemplateHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GoogleAdsConversionTemplateController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->string('q')->toString();
        $estadoLq = $request->string('estado_lq')->toString();

        return view('google_ads.conversion_templates.index', [
            'templates' => GoogleAdsConversionTemplate::query()
                ->withCount('histories')
                ->when($q !== '', function ($query) use ($q) {
                    $query->where(function ($innerQuery) use ($q) {
                        $innerQuery->where('name', 'like', "%{$q}%")
                            ->orWhere('category', 'like', "%{$q}%")
                            ->orWhere('type', 'like', "%{$q}%");
                    });
                })
                ->when($estadoLq !== '', fn ($query) => $query->where('estado_lq', (bool) $estadoLq))
                ->orderByDesc('estado_lq')
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'customers' => Customer::query()
                ->whereNotNull('id_Gads')
                ->where('id_Gads', '<>', '')
                ->orderBy('name')
                ->get(['id', 'name', 'id_Gads']),
            'q' => $q,
            'estadoLq' => $estadoLq,
        ]);
    }

    public function create()
    {
        return view('google_ads.conversion_templates.create', [
            'template' => new GoogleAdsConversionTemplate([
                'category' => 'SUBMIT_LEAD_FORM',
                'type' => 'UPLOAD_CLICKS',
                'google_status' => 'ENABLED',
                'primary_for_goal' => false,
                'click_through_lookback_window_days' => 30,
                'default_value' => 0,
                'default_currency_code' => 'COP',
                'always_use_default_value' => false,
                'estado_lq' => true,
            ]),
            'categoryOptions' => $this->categoryOptions(),
            'typeOptions' => $this->typeOptions(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $template = GoogleAdsConversionTemplate::create($this->validatedData($request));

        GoogleAdsConversionTemplateHistory::record([
            'template' => $template,
            'action' => 'template_created',
            'new_values' => $this->templateSnapshot($template),
            'success' => true,
        ]);

        return redirect()
            ->route('google-ads.conversion-templates.show', $template)
            ->with('success', 'Plantilla de conversion Google Ads creada correctamente.');
    }

    public function show(GoogleAdsConversionTemplate $conversionTemplate)
    {
        $conversionTemplate->load([
            'histories' => fn ($query) => $query
                ->with('customer:id,name')
                ->latest('id')
                ->limit(30),
        ]);

        return view('google_ads.conversion_templates.show', [
            'template' => $conversionTemplate,
        ]);
    }

    public function edit(GoogleAdsConversionTemplate $conversionTemplate)
    {
        return view('google_ads.conversion_templates.edit', [
            'template' => $conversionTemplate,
            'categoryOptions' => $this->categoryOptions(),
            'typeOptions' => $this->typeOptions(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function update(Request $request, GoogleAdsConversionTemplate $conversionTemplate): RedirectResponse
    {
        $oldValues = $this->templateSnapshot($conversionTemplate);

        $conversionTemplate->update($this->validatedData($request, $conversionTemplate));

        GoogleAdsConversionTemplateHistory::record([
            'template' => $conversionTemplate,
            'action' => 'template_updated',
            'old_values' => $oldValues,
            'new_values' => $this->templateSnapshot($conversionTemplate->fresh()),
            'success' => true,
        ]);

        return redirect()
            ->route('google-ads.conversion-templates.show', $conversionTemplate)
            ->with('success', 'Plantilla de conversion Google Ads actualizada correctamente.');
    }

    public function destroy(GoogleAdsConversionTemplate $conversionTemplate): RedirectResponse
    {
        $oldValues = $this->templateSnapshot($conversionTemplate);
        $templateId = $conversionTemplate->id;
        $templateName = $conversionTemplate->name;

        $conversionTemplate->delete();

        GoogleAdsConversionTemplateHistory::record([
            'google_ads_conversion_template_id' => $templateId,
            'template_name' => $templateName,
            'action' => 'template_deleted',
            'old_values' => $oldValues,
            'success' => true,
        ]);

        return redirect()
            ->route('google-ads.conversion-templates.index')
            ->with('success', 'Plantilla de conversion Google Ads eliminada correctamente.');
    }

    public function syncCustomers(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
        ]);

        $customers = Customer::query()
            ->whereNotNull('id_Gads')
            ->where('id_Gads', '<>', '')
            ->when(! empty($data['customer_id']), fn ($query) => $query->whereKey($data['customer_id']))
            ->orderBy('id')
            ->get(['id', 'name', 'id_Gads']);

        $user = auth()->user();

        foreach ($customers as $customer) {
            EnsureGoogleAdsConversionTemplatesJob::dispatch(
                $customer->id,
                'user',
                $user?->id,
                $user?->name
            );
        }

        GoogleAdsConversionTemplateHistory::record([
            'action' => 'template_sync_dispatched',
            'payload' => [
                'customer_id' => $data['customer_id'] ?? null,
                'customers_count' => $customers->count(),
            ],
            'success' => true,
        ]);

        return back()->with('success', "Sincronizacion de plantillas enviada a cola tracking para {$customers->count()} customer(s).");
    }

    private function validatedData(Request $request, ?GoogleAdsConversionTemplate $template = null): array
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('google_ads_conversion_templates', 'name')->ignore($template?->id),
            ],
            'category' => ['required', 'string', Rule::in($this->categoryOptions())],
            'type' => ['required', 'string', Rule::in($this->typeOptions())],
            'google_status' => ['required', 'string', Rule::in($this->statusOptions())],
            'primary_for_goal' => ['nullable', 'boolean'],
            'click_through_lookback_window_days' => ['required', 'integer', 'min:1', 'max:90'],
            'default_value' => ['required', 'numeric', 'min:0'],
            'default_currency_code' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'always_use_default_value' => ['nullable', 'boolean'],
            'estado_lq' => ['nullable', 'boolean'],
        ]);

        $data['name'] = trim($data['name']);
        $data['category'] = strtoupper($data['category']);
        $data['type'] = strtoupper($data['type']);
        $data['google_status'] = strtoupper($data['google_status']);
        $data['default_currency_code'] = strtoupper($data['default_currency_code']);
        $data['primary_for_goal'] = $request->boolean('primary_for_goal');
        $data['always_use_default_value'] = $request->boolean('always_use_default_value');
        $data['estado_lq'] = $request->boolean('estado_lq');

        return $data;
    }

    private function templateSnapshot(?GoogleAdsConversionTemplate $template): array
    {
        if (! $template) {
            return [];
        }

        return [
            'name' => $template->name,
            'category' => $template->category,
            'type' => $template->type,
            'status' => $template->google_status,
            'primaryForGoal' => (bool) $template->primary_for_goal,
            'clickThroughLookbackWindowDays' => (int) $template->click_through_lookback_window_days,
            'valueSettings' => [
                'defaultValue' => (float) $template->default_value,
                'defaultCurrencyCode' => $template->default_currency_code,
                'alwaysUseDefaultValue' => (bool) $template->always_use_default_value,
            ],
            'estado_lq' => (bool) $template->estado_lq,
        ];
    }

    private function categoryOptions(): array
    {
        return [
            'ADD_TO_CART',
            'BEGIN_CHECKOUT',
            'BOOK_APPOINTMENT',
            'CONTACT',
            'CONVERTED_LEAD',
            'DEFAULT',
            'DOWNLOAD',
            'ENGAGEMENT',
            'GET_DIRECTIONS',
            'IMPORTED_LEAD',
            'OUTBOUND_CLICK',
            'PAGE_VIEW',
            'PHONE_CALL_LEAD',
            'PURCHASE',
            'QUALIFIED_LEAD',
            'REQUEST_QUOTE',
            'SIGNUP',
            'STORE_SALE',
            'STORE_VISIT',
            'SUBMIT_LEAD_FORM',
            'SUBSCRIBE_PAID',
            'YOUTUBE_FOLLOW_ON_VIEWS',
        ];
    }

    private function typeOptions(): array
    {
        return [
            'UPLOAD_CLICKS',
        ];
    }

    private function statusOptions(): array
    {
        return [
            'ENABLED',
            'HIDDEN',
        ];
    }
}
