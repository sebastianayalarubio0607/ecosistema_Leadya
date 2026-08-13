<?php

namespace Tests\Unit;

use App\Http\Requests\GeneralLeadsDashboardFilterRequest;
use App\Http\Services\GeneralLeads\GeneralLeadsAdsLiveMetricsService;
use App\Http\Services\GeneralLeads\GeneralLeadsDashboardService;
use App\Http\Services\GeneralLeads\GeneralLeadsFilters;
use App\Http\Services\GeneralLeads\GeneralLeadsLeadQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
}
