<?php

namespace App\Http\Services\AiConnectors;

use App\Http\Services\GeneralLeads\GeneralLeadsDashboardService;
use App\Http\Services\GeneralLeads\GeneralLeadsFilters;
use App\Models\AiConnector;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AiConnectorGeneralLeadsToolService
{
    private const FILTER_KEYS = [
        'customer_id',
        'integration_id',
        'from',
        'to',
        'source',
        'campaign_origin',
        'plataforma',
        'crm_state',
        'qualification',
        'lenguaje',
        'geo',
    ];

    public function __construct(
        private readonly GeneralLeadsDashboardService $dashboard,
        private readonly AiConnectorPayloadSanitizer $sanitizer,
    ) {}

    public function tools(AiConnector $connector): array
    {
        return collect($this->definitions())
            ->filter(fn (array $definition) => $connector->allowsTool($definition['name']))
            ->values()
            ->all();
    }

    public function call(AiConnector $connector, string $tool, array $arguments): array
    {
        if (! $connector->allowsTool($tool)) {
            throw new \InvalidArgumentException('Esta herramienta no esta habilitada para el conector.');
        }

        if (in_array($tool, AiConnector::AD_TOOLS, true) && ! $connector->allow_ad_metrics) {
            throw new \InvalidArgumentException('Las metricas de pauta no estan habilitadas para este conector.');
        }

        return match ($tool) {
            AiConnector::TOOL_SNAPSHOT => $this->snapshot($connector, $arguments),
            AiConnector::TOOL_SUMMARY => $this->summary($connector, $arguments),
            AiConnector::TOOL_BREAKDOWNS => $this->breakdowns($connector, $arguments),
            AiConnector::TOOL_FUNNELS => $this->funnels($connector, $arguments),
            AiConnector::TOOL_FILTER_OPTIONS => $this->filterOptions($connector, $arguments),
            AiConnector::TOOL_COSTS => $this->costs($connector, $arguments),
            AiConnector::TOOL_AD_METRICS => $this->adMetrics($connector, $arguments),
            default => throw new \InvalidArgumentException('Herramienta MCP no soportada.'),
        };
    }

    public function normalizeArguments(AiConnector $connector, array $arguments, array $extraAllowed = []): array
    {
        $arguments = Arr::except($arguments, ['_meta']);
        $allowedKeys = array_merge(self::FILTER_KEYS, $extraAllowed);
        $unknown = array_values(array_diff(array_keys($arguments), $allowedKeys));

        if ($unknown !== []) {
            throw new \InvalidArgumentException('Parametros no permitidos: '.implode(', ', $unknown));
        }

        $rules = [
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'integration_id' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:255'],
            'campaign_origin' => ['nullable', 'string', 'max:255'],
            'plataforma' => ['nullable', 'string', 'max:255'],
            'crm_state' => ['nullable', 'string', 'max:255'],
            'qualification' => ['nullable', 'integer', 'min:1'],
            'lenguaje' => ['nullable', 'string', 'max:255'],
            'geo' => ['nullable', 'string', 'max:255'],
            'include_costs' => ['nullable', 'boolean'],
            'section' => ['nullable', 'string', Rule::in([
                'meta_campaigns',
                'meta_ad_sets',
                'meta_ads',
                'google_campaigns',
                'google_ad_groups',
                'google_ads',
            ])],
            'sort' => ['nullable', 'string', Rule::in([
                'name',
                'cost',
                'impressions',
                'clicks',
                'ctr',
                'cpc',
                'cpm',
                'conversions',
                'roas',
                'leads',
                'qualified_leads',
                'unqualified_leads',
                'cpl',
            ])],
            'dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];

        $validator = Validator::make($arguments, Arr::only($rules, $allowedKeys));

        if ($validator->fails()) {
            throw new \InvalidArgumentException($validator->errors()->first());
        }

        $query = array_filter(Arr::only($validator->validated(), self::FILTER_KEYS), fn ($value) => $value !== null && $value !== '');

        if ($connector->customer_id) {
            $query['customer_id'] = (int) $connector->customer_id;
        }

        $request = $this->request($query);
        $filters = GeneralLeadsFilters::fromRequest($request);
        $rangeDays = $filters->from->diffInDays($filters->to) + 1;

        if ($rangeDays > max(1, (int) $connector->max_date_range_days)) {
            throw new \InvalidArgumentException('El rango de fechas supera el maximo permitido para este conector.');
        }

        return array_merge($query, Arr::only($validator->validated(), $extraAllowed));
    }

    private function snapshot(AiConnector $connector, array $arguments): array
    {
        $query = $this->normalizeArguments($connector, $arguments, ['include_costs']);
        [$request, $filters] = $this->requestAndFilters($query);
        $dashboard = $this->dashboard->build($request, $filters, includeAds: false, includeLiveCosts: false);

        $payload = [
            'tool' => AiConnector::TOOL_SNAPSHOT,
            'generated_at' => now(config('app.timezone'))->toIso8601String(),
            'period' => $dashboard['header']['period'],
            'filters' => $filters->query(),
            'summary' => $this->summaryPayload($dashboard['summary']),
            'breakdowns' => $this->breakdownPayload($dashboard['breakdowns']),
            'funnels' => $this->funnelsPayload($dashboard['funnels']),
            'notes' => $dashboard['notes'] ?? [],
        ];

        if (($query['include_costs'] ?? false) && $connector->allow_ad_metrics && $connector->allowsTool(AiConnector::TOOL_COSTS)) {
            $payload['costs'] = $this->costPayload($this->dashboard->costSummary($filters));
        }

        return $this->sanitizer->sanitize($payload);
    }

    private function summary(AiConnector $connector, array $arguments): array
    {
        $query = $this->normalizeArguments($connector, $arguments);
        [$request, $filters] = $this->requestAndFilters($query);
        $dashboard = $this->dashboard->build($request, $filters, includeAds: false, includeLiveCosts: false);

        return $this->sanitizer->sanitize([
            'tool' => AiConnector::TOOL_SUMMARY,
            'generated_at' => now(config('app.timezone'))->toIso8601String(),
            'period' => $dashboard['header']['period'],
            'filters' => $filters->query(),
            'summary' => $this->summaryPayload($dashboard['summary']),
        ]);
    }

    private function breakdowns(AiConnector $connector, array $arguments): array
    {
        $query = $this->normalizeArguments($connector, $arguments);
        [$request, $filters] = $this->requestAndFilters($query);
        $dashboard = $this->dashboard->build($request, $filters, includeAds: false, includeLiveCosts: false);

        return $this->sanitizer->sanitize([
            'tool' => AiConnector::TOOL_BREAKDOWNS,
            'generated_at' => now(config('app.timezone'))->toIso8601String(),
            'period' => $dashboard['header']['period'],
            'filters' => $filters->query(),
            'breakdowns' => $this->breakdownPayload($dashboard['breakdowns']),
        ]);
    }

    private function funnels(AiConnector $connector, array $arguments): array
    {
        $query = $this->normalizeArguments($connector, $arguments);
        [$request, $filters] = $this->requestAndFilters($query);
        $dashboard = $this->dashboard->build($request, $filters, includeAds: false, includeLiveCosts: false);

        return $this->sanitizer->sanitize([
            'tool' => AiConnector::TOOL_FUNNELS,
            'generated_at' => now(config('app.timezone'))->toIso8601String(),
            'period' => $dashboard['header']['period'],
            'filters' => $filters->query(),
            'funnels' => $this->funnelsPayload($dashboard['funnels']),
        ]);
    }

    private function filterOptions(AiConnector $connector, array $arguments): array
    {
        $query = $this->normalizeArguments($connector, $arguments);
        [$request, $filters] = $this->requestAndFilters($query);
        $shell = $this->dashboard->shell($filters);

        if ($connector->customer_id) {
            $shell['filters']['customers'] = array_values(array_filter(
                $shell['filters']['customers'],
                fn ($option) => (string) ($option['value'] ?? '') === (string) $connector->customer_id
            ));
        }

        return $this->sanitizer->sanitize([
            'tool' => AiConnector::TOOL_FILTER_OPTIONS,
            'generated_at' => now(config('app.timezone'))->toIso8601String(),
            'filters' => $shell['filters'],
        ]);
    }

    private function costs(AiConnector $connector, array $arguments): array
    {
        $query = $this->normalizeArguments($connector, $arguments);
        [, $filters] = $this->requestAndFilters($query);

        return $this->sanitizer->sanitize([
            'tool' => AiConnector::TOOL_COSTS,
            'generated_at' => now(config('app.timezone'))->toIso8601String(),
            'filters' => $filters->query(),
            'costs' => $this->costPayload($this->dashboard->costSummary($filters)),
        ]);
    }

    private function adMetrics(AiConnector $connector, array $arguments): array
    {
        $query = $this->normalizeArguments($connector, $arguments, ['section', 'sort', 'dir']);
        $section = $query['section'] ?? 'meta_campaigns';
        $sort = $query['sort'] ?? 'cost';
        $dir = $query['dir'] ?? 'desc';

        [$request, $filters] = $this->requestAndFilters(array_merge($query, [
            'sort' => [$section => $sort],
            'dir' => [$section => $dir],
        ]));

        $table = $this->dashboard->adTable($filters, $request, $section);

        return $this->sanitizer->sanitize([
            'tool' => AiConnector::TOOL_AD_METRICS,
            'generated_at' => now(config('app.timezone'))->toIso8601String(),
            'filters' => $filters->query(),
            'ad_metrics' => [
                'title' => $table['title'],
                'section' => $table['section'],
                'sort' => $table['sort'],
                'dir' => $table['dir'],
                'totals' => $table['totals'],
                'rows' => $table['rows'],
            ],
        ]);
    }

    private function summaryPayload(array $summary): array
    {
        return [
            'total' => (int) ($summary['total'] ?? 0),
            'managed' => (int) ($summary['managed'] ?? 0),
            'unmanaged' => (int) ($summary['unmanaged'] ?? 0),
            'not_effective' => (int) ($summary['not_effective'] ?? 0),
            'effective' => (int) ($summary['effective'] ?? 0),
            'sales' => [
                'count' => (int) data_get($summary, 'sales.count', 0),
                'value' => [
                    'formatted' => (string) data_get($summary, 'sales.value', '$ 0,00'),
                    'numeric' => $this->moneyToFloat((string) data_get($summary, 'sales.value', '$ 0,00')),
                ],
            ],
            'missing_funnels' => $summary['missing_funnels'] ?? [],
        ];
    }

    private function breakdownPayload(array $breakdowns): array
    {
        return collect($breakdowns)
            ->map(fn ($breakdown) => [
                'totals' => $breakdown['totals'] ?? ['total' => 0, 'qualified' => 0, 'unqualified' => 0],
                'rows' => collect($breakdown['rows'] ?? [])
                    ->map(fn ($row) => Arr::only($row, ['name', 'value', 'total', 'qualified', 'unqualified']))
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    private function funnelsPayload(array $funnels): array
    {
        return [
            'current' => collect($funnels['current'] ?? [])
                ->map(fn ($row) => Arr::only($row, ['name', 'total']))
                ->values()
                ->all(),
            'history' => collect($funnels['history'] ?? [])
                ->map(fn ($row) => Arr::only($row, ['name', 'total']))
                ->values()
                ->all(),
            'daily' => [
                'labels' => data_get($funnels, 'daily.labels', []),
                'series' => collect(data_get($funnels, 'daily.series', []))
                    ->map(fn ($row) => Arr::only($row, ['name', 'data']))
                    ->values()
                    ->all(),
            ],
        ];
    }

    private function costPayload(array $costs): array
    {
        return collect($costs)
            ->map(fn ($value) => [
                'formatted' => (string) $value,
                'numeric' => $this->moneyToFloat((string) $value),
            ])
            ->all();
    }

    private function requestAndFilters(array $query): array
    {
        $request = $this->request($query);

        return [$request, GeneralLeadsFilters::fromRequest($request)];
    }

    private function request(array $query): Request
    {
        return Request::create(route('dashboard.general-leads', absolute: false), 'GET', $query);
    }

    private function moneyToFloat(string $value): float
    {
        $normalized = trim(str_replace(['$', ' '], '', $value));
        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private function definitions(): array
    {
        $filters = $this->filterSchema();

        return [
            [
                'name' => AiConnector::TOOL_SNAPSHOT,
                'title' => 'Dashboard general agregado',
                'description' => 'Devuelve resumen, desgloses y funnels agregados de dashboard/general-leads sin datos personales de leads.',
                'inputSchema' => $this->objectSchema(array_merge($filters, [
                    'include_costs' => [
                        'type' => 'boolean',
                        'description' => 'Incluye costos agregados solo si el conector tiene metricas de pauta habilitadas.',
                    ],
                ])),
            ],
            [
                'name' => AiConnector::TOOL_SUMMARY,
                'title' => 'Resumen general de leads',
                'description' => 'Consulta totales agregados de leads, gestionados, no gestionados, efectivos y ventas.',
                'inputSchema' => $this->objectSchema($filters),
            ],
            [
                'name' => AiConnector::TOOL_BREAKDOWNS,
                'title' => 'Desgloses generales',
                'description' => 'Consulta desgloses agregados por source, origen y medio sin datos de leads individuales.',
                'inputSchema' => $this->objectSchema($filters),
            ],
            [
                'name' => AiConnector::TOOL_FUNNELS,
                'title' => 'Funnels agregados',
                'description' => 'Consulta estado actual, historico y serie diaria de funnels agregados.',
                'inputSchema' => $this->objectSchema($filters),
            ],
            [
                'name' => AiConnector::TOOL_FILTER_OPTIONS,
                'title' => 'Opciones de filtros',
                'description' => 'Lista opciones disponibles para filtrar consultas agregadas.',
                'inputSchema' => $this->objectSchema($filters),
            ],
            [
                'name' => AiConnector::TOOL_COSTS,
                'title' => 'Costos agregados',
                'description' => 'Consulta costos totales de Meta, Google y total. No devuelve leads individuales.',
                'inputSchema' => $this->objectSchema($filters),
            ],
            [
                'name' => AiConnector::TOOL_AD_METRICS,
                'title' => 'Metricas agregadas de pauta',
                'description' => 'Consulta metricas agregadas por campana, grupo o anuncio. No devuelve informacion personal de leads.',
                'inputSchema' => $this->objectSchema(array_merge($filters, [
                    'section' => [
                        'type' => 'string',
                        'enum' => ['meta_campaigns', 'meta_ad_sets', 'meta_ads', 'google_campaigns', 'google_ad_groups', 'google_ads'],
                    ],
                    'sort' => [
                        'type' => 'string',
                        'enum' => ['name', 'cost', 'impressions', 'clicks', 'ctr', 'cpc', 'cpm', 'conversions', 'roas', 'leads', 'qualified_leads', 'unqualified_leads', 'cpl'],
                    ],
                    'dir' => [
                        'type' => 'string',
                        'enum' => ['asc', 'desc'],
                    ],
                ])),
            ],
        ];
    }

    private function filterSchema(): array
    {
        return [
            'customer_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Cliente. Si el conector esta fijado a un cliente, este valor sera ignorado.'],
            'integration_id' => ['type' => 'integer', 'minimum' => 1],
            'from' => ['type' => 'string', 'description' => 'Fecha inicial. Formatos aceptados: YYYY-MM-DD o YYYY-MM-DDTHH:mm.'],
            'to' => ['type' => 'string', 'description' => 'Fecha final. Formatos aceptados: YYYY-MM-DD o YYYY-MM-DDTHH:mm.'],
            'source' => ['type' => 'string', 'maxLength' => 255],
            'campaign_origin' => ['type' => 'string', 'maxLength' => 255],
            'plataforma' => ['type' => 'string', 'maxLength' => 255],
            'crm_state' => ['type' => 'string', 'maxLength' => 255],
            'qualification' => ['type' => 'integer', 'minimum' => 1],
            'lenguaje' => ['type' => 'string', 'maxLength' => 255],
            'geo' => ['type' => 'string', 'maxLength' => 255],
        ];
    }

    private function objectSchema(array $properties): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => $properties,
        ];
    }
}
