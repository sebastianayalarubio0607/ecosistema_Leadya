<?php

namespace Tests\Unit;

use App\Http\Requests\GeneralLeadsDashboardFilterRequest;
use App\Http\Services\GeneralLeads\GeneralLeadsFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

    public function test_sort_validation_uses_a_whitelist(): void
    {
        $request = new GeneralLeadsDashboardFilterRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey('sort.*', $rules);
        $this->assertStringContainsString('in:name,cost,impressions,conversions,roas,leads,qualified_leads,unqualified_leads,cpl', implode('|', $rules['sort.*']));
    }

    public function test_dashboard_gerencial_leads_route_name_is_preserved(): void
    {
        $this->assertSame('/dashboard/gerencial-leads', route('dashboard.gerencial-leads', absolute: false));
        $this->assertSame('/dashboard/general-leads', route('dashboard.general-leads', absolute: false));
    }
}
