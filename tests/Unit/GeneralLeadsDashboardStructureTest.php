<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardGerencialLeadsController;
use App\Http\Requests\GeneralLeadsDashboardFilterRequest;
use App\Http\Services\GeneralLeads\GeneralLeadsAdsLiveMetricsService;
use App\Http\Services\GeneralLeads\GeneralLeadsDashboardService;
use App\Http\Services\GeneralLeads\GeneralLeadsFilters;
use App\Http\Services\GeneralLeads\GeneralLeadsLeadQuery;
use App\Http\Services\GeneralLeads\GeneralLeadsPresentation;
use App\Models\Lead;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class GeneralLeadsDashboardStructureTest extends TestCase
{
    public function test_default_period_is_last_seven_days_in_configured_timezone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-31 12:00:00', config('app.timezone')));

        $filters = GeneralLeadsFilters::fromRequest(Request::create('/dashboard/general-leads'));

        $this->assertSame('2026-07-24 12:00', $filters->from->format('Y-m-d H:i'));
        $this->assertSame('2026-07-31 12:00', $filters->to->format('Y-m-d H:i'));

        Carbon::setTestNow();
    }

    public function test_filter_query_keeps_leads_list_compatible_parameters(): void
    {
        $filters = GeneralLeadsFilters::fromRequest(Request::create('/dashboard/general-leads', 'GET', [
            'customer_id' => 5,
            'from' => '2026-07-30T10:15',
            'to' => '2026-07-31T10:15',
            'source' => 'google',
            'campaign_origin' => 'search',
            'plataforma' => 'gads',
        ]));

        $this->assertSame([
            'customer_id' => 5,
            'from' => '2026-07-30T10:15',
            'to' => '2026-07-31T10:15',
            'source' => 'google',
            'campaign_origin' => 'search',
            'plataforma' => 'gads',
        ], $filters->query());
    }

    public function test_filter_dates_are_normalized_when_user_sends_reversed_range(): void
    {
        $filters = GeneralLeadsFilters::fromRequest(Request::create('/dashboard/general-leads', 'GET', [
            'from' => '2026-08-12T10:30',
            'to' => '2026-08-01T07:35',
        ]));

        $this->assertSame('2026-08-01T07:35', $filters->query()['from']);
        $this->assertSame('2026-08-12T10:30', $filters->query()['to']);
    }

    public function test_sort_validation_uses_a_whitelist(): void
    {
        $request = new GeneralLeadsDashboardFilterRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('sort.*', $rules);
        $this->assertStringContainsString('in:name,cost,impressions,clicks,ctr,cpc,cpm,conversions,roas,leads,qualified_leads,unqualified_leads,cpl', implode('|', $rules['sort.*']));
    }

    public function test_dashboard_gerencial_leads_route_name_is_preserved(): void
    {
        $this->assertSame('/dashboard/gerencial-leads', route('dashboard.gerencial-leads', absolute: false));
        $this->assertSame('/dashboard/general-leads', route('dashboard.general-leads', absolute: false));
    }

    public function test_ads_table_sorting_uses_stable_name_tiebreaker(): void
    {
        $service = new GeneralLeadsDashboardService(new GeneralLeadsLeadQuery);
        $method = new ReflectionMethod($service, 'formatAds');
        $method->setAccessible(true);

        $table = $method->invoke(
            $service,
            Request::create('/dashboard/general-leads', 'GET', [
                'sort' => ['meta_campaigns' => 'cost'],
                'dir' => ['meta_campaigns' => 'desc'],
            ]),
            GeneralLeadsFilters::fromRequest(Request::create('/dashboard/general-leads', 'GET', [
                'customer_id' => 5,
                'from' => '2026-07-30T10:15',
                'to' => '2026-07-31T10:15',
            ])),
            'meta_campaigns',
            'Campañas Meta',
            new Collection([
                20 => (object) ['name_value' => 'Zulu', 'cost_value' => 10, 'impressions_value' => 1, 'conversions_value' => 0, 'roas_value' => null],
                10 => (object) ['name_value' => 'Alpha', 'cost_value' => 10, 'impressions_value' => 1, 'conversions_value' => 0, 'roas_value' => null],
                30 => (object) ['name_value' => 'Bravo', 'cost_value' => 5, 'impressions_value' => 1, 'conversions_value' => 0, 'roas_value' => null],
            ]),
            new Collection
        );

        $this->assertSame(['Alpha', 'Zulu', 'Bravo'], array_column($table['rows'], 'name'));
        $this->assertStringContainsString('ad_section=meta_campaigns', $table['rows'][0]['url']);
        $this->assertStringContainsString('ad_entity_id=10', $table['rows'][0]['url']);
    }

    public function test_missing_catalog_breakdown_labels_include_the_raw_value(): void
    {
        $originalDatabaseConfig = [
            'database.default' => config('database.default'),
            'database.connections.sqlite.database' => config('database.connections.sqlite.database'),
            'database.connections.sqlite.foreign_key_constraints' => config('database.connections.sqlite.foreign_key_constraints'),
        ];

        try {
            $this->prepareHistoricalSalesSchema();

            Schema::create('origins', function (Blueprint $table) {
                $table->id();
                $table->string('code')->nullable();
                $table->string('name')->nullable();
            });

            Schema::create('platforms', function (Blueprint $table) {
                $table->id();
                $table->string('code')->nullable();
                $table->string('name')->nullable();
            });

            DB::table('leads')->insert([
                ['id' => 1, 'campaign_origin' => 'chatgpt', 'plataforma' => 'whatsapp-bot', 'crm_state' => null, 'created_at' => '2026-08-01 09:00:00', 'updated_at' => '2026-08-01 09:00:00'],
            ]);

            $filters = new GeneralLeadsFilters(
                customerId: null,
                integrationId: null,
                from: Carbon::parse('2026-08-01 00:00:00'),
                to: Carbon::parse('2026-08-01 23:59:59'),
                source: null,
                campaignOrigin: null,
                platform: null,
                crmState: null,
                qualification: null,
                language: null,
                geo: null,
            );

            $service = new GeneralLeadsDashboardService(new GeneralLeadsLeadQuery);
            $breakdown = new ReflectionMethod($service, 'breakdown');
            $breakdown->setAccessible(true);

            $originRows = $breakdown->invoke($service, $filters, 'origin')['rows'];
            $typeRows = $breakdown->invoke($service, $filters, 'type')['rows'];

            $this->assertSame('Origen No Creado(chatgpt)', $originRows[0]['name']);
            $this->assertSame(GeneralLeadsPresentation::MISSING_ORIGIN, $originRows[0]['value']);
            $this->assertSame('Medio No Creado(whatsapp-bot)', $typeRows[0]['name']);
            $this->assertSame(GeneralLeadsPresentation::MISSING_TYPE, $typeRows[0]['value']);
        } finally {
            DB::purge('sqlite');
            config($originalDatabaseConfig);
        }
    }

    public function test_meta_results_nested_values_are_used_as_total_conversions(): void
    {
        $service = (new \ReflectionClass(GeneralLeadsAdsLiveMetricsService::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($service, 'metaResults');
        $method->setAccessible(true);

        $value = $method->invoke($service, [
            'objective' => 'OUTCOME_LEADS',
            'results' => [
                [
                    'indicator' => 'actions:offsite_conversion.fb_pixel_lead',
                    'values' => [
                        ['value' => '248', 'attribution_windows' => ['default']],
                    ],
                ],
            ],
            'actions' => [
                ['action_type' => 'lead', 'value' => '248'],
            ],
        ]);

        $this->assertSame(248.0, $value);
    }

    public function test_google_entity_name_falls_back_to_id_when_missing(): void
    {
        $service = (new \ReflectionClass(GeneralLeadsAdsLiveMetricsService::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($service, 'googleEntityName');
        $method->setAccessible(true);

        $this->assertSame('123456', $method->invoke($service, null, '123456'));
        $this->assertSame('123456', $method->invoke($service, ' Sin Nombre ', '123456'));
        $this->assertSame('Marca Search', $method->invoke($service, 'Marca Search', '123456'));
    }

    public function test_google_ad_table_formats_conversion_events(): void
    {
        $service = new GeneralLeadsDashboardService(new GeneralLeadsLeadQuery);
        $method = new ReflectionMethod($service, 'formatAds');
        $method->setAccessible(true);

        $table = $method->invoke(
            $service,
            Request::create('/dashboard/general-leads', 'GET'),
            GeneralLeadsFilters::fromRequest(Request::create('/dashboard/general-leads', 'GET', [
                'customer_id' => 5,
                'from' => '2026-07-30T10:15',
                'to' => '2026-07-31T10:15',
            ])),
            'google_campaigns',
            'Campañas Google',
            new Collection([
                '123456' => (object) [
                    'name_value' => 'Marca Search',
                    'cost_value' => 100,
                    'impressions_value' => 1000,
                    'clicks_value' => 50,
                    'conversions_value' => 3.5,
                    'roas_value' => null,
                    'conversion_events_value' => [
                        ['event_id' => 'lead', 'name' => 'Lead', 'conversions_value' => 2],
                        ['event_id' => 'sale', 'name' => 'Venta', 'conversions_value' => 1.5],
                    ],
                ],
            ]),
            new Collection
        );

        $this->assertSame('Lead', $table['rows'][0]['conversion_events'][0]['name']);
        $this->assertSame('2,00', $table['rows'][0]['conversion_events'][0]['quantity']);
        $this->assertSame('Venta', $table['rows'][0]['conversion_events'][1]['name']);
        $this->assertSame('1,50', $table['rows'][0]['conversion_events'][1]['quantity']);
        $this->assertSame('3,50', $table['rows'][0]['conversions']);
        $this->assertSame('Lead', $table['totals']['conversion_events'][0]['name']);
    }

    public function test_sales_summary_uses_historical_sales_funnel_leads(): void
    {
        $originalDatabaseConfig = [
            'database.default' => config('database.default'),
            'database.connections.sqlite.database' => config('database.connections.sqlite.database'),
            'database.connections.sqlite.foreign_key_constraints' => config('database.connections.sqlite.foreign_key_constraints'),
        ];

        try {
            $this->prepareHistoricalSalesSchema();

            DB::table('funnels')->insert([
                ['id' => 1, 'name' => 'Venta', 'created_at' => now(), 'updated_at' => now()],
                ['id' => 2, 'name' => 'Ventas', 'created_at' => now(), 'updated_at' => now()],
                ['id' => 3, 'name' => 'Oportunidades', 'created_at' => now(), 'updated_at' => now()],
            ]);

            DB::table('leads')->insert([
                ['id' => 1, 'value' => 100, 'crm_state' => null, 'created_at' => '2026-08-01 09:00:00', 'updated_at' => '2026-08-01 09:00:00'],
                ['id' => 2, 'value' => 200, 'crm_state' => null, 'created_at' => '2026-08-01 10:00:00', 'updated_at' => '2026-08-01 10:00:00'],
                ['id' => 3, 'value' => 300, 'crm_state' => 'current-sale', 'created_at' => '2026-08-01 11:00:00', 'updated_at' => '2026-08-01 11:00:00'],
                ['id' => 4, 'value' => 400, 'crm_state' => null, 'created_at' => '2026-07-31 23:00:00', 'updated_at' => '2026-07-31 23:00:00'],
            ]);

            DB::table('lead_funnel_histories')->insert([
                ['lead_id' => 1, 'funnel_id' => 1, 'created_at' => '2026-08-01 09:30:00', 'updated_at' => '2026-08-01 09:30:00'],
                ['lead_id' => 2, 'funnel_id' => 2, 'created_at' => '2026-08-01 10:30:00', 'updated_at' => '2026-08-01 10:30:00'],
                ['lead_id' => 3, 'funnel_id' => 3, 'created_at' => '2026-08-01 11:30:00', 'updated_at' => '2026-08-01 11:30:00'],
                ['lead_id' => 4, 'funnel_id' => 1, 'created_at' => '2026-08-01 12:30:00', 'updated_at' => '2026-08-01 12:30:00'],
            ]);

            $filters = new GeneralLeadsFilters(
                customerId: null,
                integrationId: null,
                from: Carbon::parse('2026-08-01 00:00:00'),
                to: Carbon::parse('2026-08-01 23:59:59'),
                source: null,
                campaignOrigin: null,
                platform: null,
                crmState: null,
                qualification: null,
                language: null,
                geo: null,
            );

            $generalService = new GeneralLeadsDashboardService(new GeneralLeadsLeadQuery);
            $generalSales = new ReflectionMethod($generalService, 'sales');
            $generalSales->setAccessible(true);

            $this->assertSame([
                'count' => 2,
                'value' => '$ 300,00',
            ], $generalSales->invoke($generalService, $filters, [1, 2]));

            $controller = new DashboardGerencialLeadsController;
            $base = Lead::query()
                ->from('leads')
                ->whereBetween('leads.created_at', [$filters->from, $filters->to]);

            $countHistorical = new ReflectionMethod($controller, 'countHistoricalLeadsByFunnelIds');
            $countHistorical->setAccessible(true);
            $sumHistorical = new ReflectionMethod($controller, 'sumHistoricalLeadsValueByFunnelIds');
            $sumHistorical->setAccessible(true);

            $this->assertSame(2, $countHistorical->invoke($controller, $base, 'leads', [1, 2], $filters->from, $filters->to));
            $this->assertSame(300.0, $sumHistorical->invoke($controller, $base, 'leads', [1, 2], $filters->from, $filters->to));
        } finally {
            DB::purge('sqlite');
            config($originalDatabaseConfig);
        }
    }

    public function test_general_funnel_sections_route_empty_current_state_and_missing_history_differently(): void
    {
        $originalDatabaseConfig = [
            'database.default' => config('database.default'),
            'database.connections.sqlite.database' => config('database.connections.sqlite.database'),
            'database.connections.sqlite.foreign_key_constraints' => config('database.connections.sqlite.foreign_key_constraints'),
        ];

        try {
            $this->prepareHistoricalSalesSchema();

            DB::table('funnels')->insert([
                ['id' => 5, 'name' => 'Leads', 'created_at' => now(), 'updated_at' => now()],
                ['id' => 7, 'name' => 'Oportunidades', 'created_at' => now(), 'updated_at' => now()],
            ]);

            DB::table('qualification')->insert([
                ['id' => 1, 'name' => 'Calificado', 'funnel_id' => 7, 'created_at' => now(), 'updated_at' => now()],
            ]);

            DB::table('crm_state')->insert([
                ['id' => 'qualified', 'name' => 'Qualified', 'qualification' => 1, 'unmanaged' => false, 'created_at' => now(), 'updated_at' => now()],
            ]);

            DB::table('leads')->insert([
                ['id' => 1, 'crm_state' => null, 'created_at' => '2026-08-01 09:00:00', 'updated_at' => '2026-08-01 09:00:00'],
                ['id' => 2, 'crm_state' => 'qualified', 'created_at' => '2026-08-01 10:00:00', 'updated_at' => '2026-08-01 10:00:00'],
                ['id' => 3, 'crm_state' => null, 'created_at' => '2026-08-01 11:00:00', 'updated_at' => '2026-08-01 11:00:00'],
                ['id' => 4, 'crm_state' => null, 'created_at' => '2026-08-01 12:00:00', 'updated_at' => '2026-08-01 12:00:00'],
            ]);

            DB::table('lead_funnel_histories')->insert([
                ['lead_id' => 1, 'funnel_id' => null, 'created_at' => '2026-08-01 09:30:00', 'updated_at' => '2026-08-01 09:30:00'],
                ['lead_id' => 2, 'funnel_id' => 7, 'created_at' => '2026-08-01 10:30:00', 'updated_at' => '2026-08-01 10:30:00'],
                ['lead_id' => 3, 'funnel_id' => 99, 'created_at' => '2026-08-01 11:30:00', 'updated_at' => '2026-08-01 11:30:00'],
                ['lead_id' => 4, 'funnel_id' => 5, 'created_at' => '2026-08-01 12:30:00', 'updated_at' => '2026-08-01 12:30:00'],
            ]);

            $filters = new GeneralLeadsFilters(
                customerId: null,
                integrationId: null,
                from: Carbon::parse('2026-08-01 00:00:00'),
                to: Carbon::parse('2026-08-01 23:59:59'),
                source: null,
                campaignOrigin: null,
                platform: null,
                crmState: null,
                qualification: null,
                language: null,
                geo: null,
            );

            $service = new GeneralLeadsDashboardService(new GeneralLeadsLeadQuery);
            $funnels = new ReflectionMethod($service, 'funnels');
            $funnels->setAccessible(true);
            $data = $funnels->invoke($service, $filters);

            $currentByName = collect($data['current'])->keyBy('name');
            $historyByName = collect($data['history'])->keyBy('name');

            $this->assertSame(3, $currentByName->get('Sin Funnel')['total']);
            $this->assertFalse($currentByName->has('Leads'));
            $this->assertSame(3, $historyByName->get('Leads')['total']);
            $this->assertSame(1, (int) $historyByName->get('Oportunidades')['total']);
        } finally {
            DB::purge('sqlite');
            config($originalDatabaseConfig);
        }
    }

    private function prepareHistoricalSalesSchema(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => false,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('funnels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('qualification', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('funnel_id')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_state', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->unsignedBigInteger('qualification')->nullable();
            $table->boolean('unmanaged')->default(false);
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('integration_id')->nullable();
            $table->string('campaign_origin')->nullable();
            $table->string('plataforma')->nullable();
            $table->string('crm_state')->nullable();
            $table->string('lenguaje')->nullable();
            $table->string('geo')->nullable();
            $table->decimal('value', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('lead_funnel_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('funnel_id')->nullable();
            $table->timestamps();
        });
    }
}
