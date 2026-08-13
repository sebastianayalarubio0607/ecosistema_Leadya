<?php

namespace App\Livewire;

use App\Http\Controllers\DashboardGerencialLeadsController;
use App\Models\Customer;
use App\Models\Origin;
use App\Models\Source;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Livewire\Attributes\Url;
use Livewire\Component;

class GerencialLeadsDashboard extends Component
{
    private const FILTER_KEYS = [
        'customer_id',
        'integration_id',
        'from',
        'to',
        'source',
        'campaign_origin',
        'plataforma',
        'lenguaje',
        'geo',
        'crm_state',
        'qualification',
    ];

    #[Url(as: 'customer_id', except: '')]
    public string $customerId = '';

    #[Url(as: 'integration_id', except: '')]
    public string $integrationId = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: '')]
    public string $source = '';

    #[Url(as: 'campaign_origin', except: '')]
    public string $campaignOrigin = '';

    #[Url(except: '')]
    public string $plataforma = '';

    #[Url(except: '')]
    public string $lenguaje = '';

    #[Url(except: '')]
    public string $geo = '';

    #[Url(as: 'crm_state', except: '')]
    public string $crmState = '';

    #[Url(except: '')]
    public string $qualification = '';

    public bool $filtersApplied = false;

    public bool $filtersDirty = false;

    public array $appliedQuery = [];

    public function mount(): void
    {
        $this->filtersApplied = request()->hasAny(self::FILTER_KEYS);
        $this->appliedQuery = $this->filtersApplied ? $this->query() : [];
    }

    public function updated(string $property): void
    {
        if ($property !== 'appliedQuery') {
            $this->filtersDirty = true;
        }
    }

    public function applyFilters(): void
    {
        $this->appliedQuery = $this->query();
        $this->filtersApplied = true;
        $this->filtersDirty = false;
        $this->dispatch('gerencial-leads-refresh');
    }

    public function clearFilters(): void
    {
        $this->reset([
            'customerId',
            'integrationId',
            'from',
            'to',
            'source',
            'campaignOrigin',
            'plataforma',
            'lenguaje',
            'geo',
            'crmState',
            'qualification',
        ]);

        $this->appliedQuery = [];
        $this->filtersApplied = false;
        $this->filtersDirty = false;
        $this->dispatch('gerencial-leads-refresh');
    }

    public function render()
    {
        $payload = $this->filtersApplied
            ? app(DashboardGerencialLeadsController::class)->livewireDashboardPayload($this->requestFromAppliedQuery(), includeAdvertisingTables: false)
            : $this->shellPayload();

        return view('livewire.gerencial-leads-dashboard', array_merge($payload, [
            'hasData' => $this->filtersApplied,
            'filtersDirty' => $this->filtersDirty,
            'filtersQuery' => $this->appliedQuery,
        ]));
    }

    private function requestFromAppliedQuery(): Request
    {
        $request = Request::create(route('dashboard.gerencial-leads', absolute: false), 'GET', $this->appliedQuery);

        if (request()->hasSession()) {
            $request->setLaravelSession(request()->session());
        } else {
            $request->setLaravelSession(new Store('gerencial-leads-livewire', new ArraySessionHandler(120)));
        }

        return $request;
    }

    private function query(): array
    {
        return array_filter([
            'customer_id' => $this->customerId,
            'integration_id' => $this->integrationId,
            'from' => $this->from,
            'to' => $this->to,
            'source' => $this->source,
            'campaign_origin' => $this->campaignOrigin,
            'plataforma' => $this->plataforma,
            'lenguaje' => $this->lenguaje,
            'geo' => $this->geo,
            'crm_state' => $this->crmState,
            'qualification' => $this->qualification,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function shellPayload(): array
    {
        $now = now(config('app.timezone') ?: 'UTC');
        $customers = Customer::query()->orderBy('name')->get(['id', 'name']);

        $customerOptions = $customers->map(fn (Customer $customer) => [
            'value' => $customer->id,
            'label' => $customer->name." (ID: {$customer->id})",
            'selected' => (string) $this->customerId === (string) $customer->id,
        ])->values()->all();

        $sourceOptions = Source::query()
            ->orderBy('name')
            ->get(['code', 'name'])
            ->map(fn (Source $source) => [
                'value' => $source->code,
                'label' => $source->name,
                'selected' => (string) $this->source === (string) $source->code,
            ])->values()->all();

        $originOptions = Origin::query()
            ->orderBy('name')
            ->get(['code', 'name'])
            ->map(fn (Origin $origin) => [
                'value' => $origin->code,
                'label' => $origin->name,
                'selected' => (string) $this->campaignOrigin === (string) $origin->code,
            ])->values()->all();

        return [
            'customers' => $customers,
            'customerId' => null,
            'integrationId' => null,
            'selectedCustomer' => null,
            'metric' => [],
            'from' => $now->copy()->subDays(7),
            'to' => $now,
            'nowMax' => $now->format('Y-m-d\TH:i'),
            'ui' => [
                'header' => [
                    'selected_customer_name' => 'Sin filtros aplicados',
                    'selected_customer_id' => null,
                ],
                'filters' => [
                    'action' => route('dashboard.gerencial-leads'),
                    'integration_id' => $this->integrationId,
                    'customer_id' => $this->customerId,
                    'from_value' => $this->from,
                    'to_value' => $this->to,
                    'now_max' => $now->format('Y-m-d\TH:i'),
                    'customer_options' => $customerOptions,
                    'source_options' => $sourceOptions,
                    'origin_options' => $originOptions,
                    'platform_options' => [],
                ],
            ],
            'metaCampaignSummary' => [],
            'metaAdGroupSummary' => [],
            'metaAdSummary' => [],
            'googleCampaignSummary' => [],
            'googleAdGroupSummary' => [],
            'googleAdSummary' => [],
        ];
    }
}
