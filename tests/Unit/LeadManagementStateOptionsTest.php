<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\LeadCrmStateController;
use App\Http\Controllers\LeadManagementController;
use App\Http\Services\GeneralLeads\GeneralLeadsDashboardService;
use App\Http\Services\GeneralLeads\GeneralLeadsLeadQuery;
use App\Http\Services\Lead\LeadFunnelHistoryService;
use App\Models\Lead;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class LeadManagementStateOptionsTest extends TestCase
{
    private array $originalDatabaseConfig;

    private LeadManagementController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalDatabaseConfig = config('database');
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => false,
        ]);
        DB::purge('sqlite');

        Schema::create('funnels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('orden')->nullable();
        });
        Schema::create('qualification', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('funnel_id')->nullable();
        });
        Schema::create('crm_state', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->unsignedBigInteger('qualification')->nullable();
            $table->unsignedBigInteger('meta_event_id')->nullable();
            $table->unsignedBigInteger('whatsapp_event_id')->nullable();
            $table->boolean('google_ads_conversion_enabled')->default(false);
            $table->string('google_ads_conversion_action_id')->nullable();
            $table->string('google_ads_conversion_action_resource_name')->nullable();
        });
        Schema::create('crm_state_google_ads_conversions', function (Blueprint $table) {
            $table->id();
            $table->string('crm_state_id');
            $table->unsignedBigInteger('customer_id');
            $table->string('conversion_action_id')->nullable();
            $table->string('conversion_action_resource_name')->nullable();
        });
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->boolean('disable_integration_id_crm_prefix')->default(false);
            $table->string('crm_id_prefix')->nullable();
        });
        DB::table('funnels')->insert(['id' => 1, 'name' => 'Oportunidades']);
        DB::table('qualification')->insert([
            ['id' => 1, 'name' => 'Calificado', 'funnel_id' => 1],
            ['id' => 2, 'name' => 'Sin funnel', 'funnel_id' => null],
        ]);
        $query = new GeneralLeadsLeadQuery;
        $this->controller = new LeadManagementController(
            new GeneralLeadsDashboardService($query),
            $query,
            Mockery::mock(LeadCrmStateController::class),
            new LeadFunnelHistoryService,
        );
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        config(['database' => $this->originalDatabaseConfig]);
        parent::tearDown();
    }

    public function test_options_require_an_event_and_funnel_and_keep_states_sharing_a_funnel(): void
    {
        $this->state('crm-meta', ['meta_event_id' => 1]);
        $this->state('crm-whatsapp', ['whatsapp_event_id' => 1]);
        $this->state('crm-none');
        $this->state('crm-no-funnel', ['qualification' => 2, 'meta_event_id' => 1]);
        $this->state('other-meta', ['meta_event_id' => 1]);

        $options = $this->stateOptions();

        $this->assertSame(['crm-meta', 'crm-whatsapp'], $options->pluck('id')->all());
        $this->assertSame(['Oportunidades', 'Oportunidades'], $options->pluck('funnel_name')->all());
        $this->assertSame(['Calificado', 'Calificado'], $options->pluck('qualification_name')->all());
    }

    public function test_google_requires_enabled_conversion_for_customer_with_legacy_fallback(): void
    {
        $this->state('crm-own', ['google_ads_conversion_enabled' => true]);
        $this->state('crm-other', ['google_ads_conversion_enabled' => true, 'google_ads_conversion_action_id' => 'legacy']);
        $this->state('crm-disabled', ['google_ads_conversion_action_id' => '123']);
        $this->state('crm-empty', ['google_ads_conversion_enabled' => true, 'google_ads_conversion_action_id' => '  ']);
        $this->state('crm-legacy', ['google_ads_conversion_enabled' => true, 'google_ads_conversion_action_id' => '123']);
        $this->state('crm-resource', ['google_ads_conversion_enabled' => true, 'google_ads_conversion_action_resource_name' => 'customers/1/conversionActions/123']);
        $this->state('crm-meta-disabled-google', ['meta_event_id' => 1]);
        DB::table('crm_state_google_ads_conversions')->insert([
            ['crm_state_id' => 'crm-own', 'customer_id' => 1, 'conversion_action_id' => '123'],
            ['crm_state_id' => 'crm-other', 'customer_id' => 2, 'conversion_action_id' => '456'],
        ]);

        $this->assertSame(['crm-legacy', 'crm-meta-disabled-google', 'crm-own', 'crm-resource'], $this->stateOptions()->pluck('id')->all());
        $this->assertSame(['crm-legacy', 'crm-meta-disabled-google', 'crm-other', 'crm-resource'], $this->stateOptions(2)->pluck('id')->all());
    }

    public function test_cached_options_do_not_leak_between_customers_sharing_prefix(): void
    {
        $this->state('crm-google', ['google_ads_conversion_enabled' => true]);
        DB::table('crm_state_google_ads_conversions')->insert([
            'crm_state_id' => 'crm-google', 'customer_id' => 1, 'conversion_action_id' => '123',
        ]);
        $leads = collect([
            new Lead(['customer_id' => 1, 'crm_id' => 'crm-101']),
            new Lead(['customer_id' => 2, 'crm_id' => 'crm-102']),
        ]);

        (new ReflectionMethod($this->controller, 'attachCrmStateOptions'))->invoke($this->controller, $leads);

        $this->assertSame(['crm-google'], $leads[0]->crm_state_options->pluck('id')->all());
        $this->assertTrue($leads[1]->crm_state_options->isEmpty());
    }

    public function test_saving_a_state_without_events_is_rejected(): void
    {
        $this->state('crm-none');
        $lead = new Lead(['customer_id' => 1, 'crm_id' => 'crm-101', 'crm_state' => 'crm-current']);
        $request = Request::create('/', 'PATCH', ['crm_state' => 'crm-none']);

        try {
            $this->controller->updateCrmState($request, $lead);
            $this->fail('An unconfigured state must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('crm_state', $exception->errors());
            $this->assertSame('crm-current', $lead->crm_state);
        }
    }

    public function test_saving_allowed_state_uses_crm_id_and_returns_qualification(): void
    {
        $this->state('crm-meta', ['meta_event_id' => 1]);
        $lead = new Lead(['customer_id' => 1, 'crm_id' => 'crm-101']);
        $tracking = Mockery::mock(LeadCrmStateController::class);
        $tracking->shouldReceive('changeLeadStateForLead')->once()
            ->with($lead, 'crm-meta', Mockery::type(LeadFunnelHistoryService::class))->andReturn(true);
        $query = new GeneralLeadsLeadQuery;
        $controller = new LeadManagementController(new GeneralLeadsDashboardService($query), $query, $tracking, new LeadFunnelHistoryService);

        $response = $controller->updateCrmState(Request::create('/', 'PATCH', ['crm_state' => 'crm-meta']), $lead);

        $this->assertSame('crm-meta', $response->getData(true)['crm_state']);
        $this->assertSame('Calificado', $response->getData(true)['qualification_name']);
    }

    public function test_options_follow_numeric_funnel_order_with_stable_ties_and_nulls_last(): void
    {
        DB::table('funnels')->where('id', 1)->update(['orden' => 10]);
        DB::table('funnels')->insert([
            ['id' => 3, 'name' => 'Ventas', 'orden' => 2],
            ['id' => 4, 'name' => 'Ventas', 'orden' => 2],
            ['id' => 5, 'name' => 'Inicial', 'orden' => null],
        ]);
        DB::table('qualification')->insert([
            ['id' => 3, 'name' => 'Venta', 'funnel_id' => 3],
            ['id' => 4, 'name' => 'Otra venta', 'funnel_id' => 4],
            ['id' => 5, 'name' => 'Inicial', 'funnel_id' => 5],
        ]);
        $this->state('crm-oportunidad', ['meta_event_id' => 1]);
        $this->state('crm-venta-b', ['qualification' => 3, 'meta_event_id' => 1]);
        $this->state('crm-venta-a', ['qualification' => 3, 'meta_event_id' => 1]);
        $this->state('crm-otra-venta', ['qualification' => 4, 'meta_event_id' => 1]);
        $this->state('crm-inicial', ['qualification' => 5, 'meta_event_id' => 1]);
        $this->state('crm-sin-eventos', ['qualification' => 3]);

        $this->assertSame([
            'crm-venta-a', 'crm-venta-b', 'crm-otra-venta', 'crm-oportunidad', 'crm-inicial',
        ], $this->stateOptions()->pluck('id')->all());
    }

    private function state(string $id, array $attributes = []): void
    {
        DB::table('crm_state')->insert(array_merge(['id' => $id, 'name' => $id, 'qualification' => 1], $attributes));
    }

    private function stateOptions(int $customerId = 1): Collection
    {
        return (new ReflectionMethod($this->controller, 'crmStateOptionsForPrefixes'))
            ->invoke($this->controller, collect(['crm']), $customerId);
    }
}
