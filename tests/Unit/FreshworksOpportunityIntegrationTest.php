<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\LeadCrmStateController;
use App\Http\Services\Integration\FreshworksOpportunityAwareIntegrationService;
use App\Http\Services\Integration\FreshworksOpportunityIntegrationService;
use App\Http\Services\Integration\FreshworksOpportunityPipelineSyncService;
use App\Http\Services\Integration\IntegrationService;
use App\Http\Services\Lead\LeadFunnelHistoryService;
use App\Models\CrmState;
use App\Models\Integration;
use App\Models\Integrationtype;
use App\Models\IntegrationVariable;
use App\Models\IntegrationVariableCondition;
use App\Models\IntegrationVariableMapping;
use App\Models\Lead;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class FreshworksOpportunityIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Isolated test database only. The configured MySQL database is never reset.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('integrationtypes', function (Blueprint $table) {$table->id(); $table->string('name'); $table->timestamps();});
        Schema::create('integrations', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('integrationtype_id'); $table->string('url'); $table->text('tokent')->nullable();
            $table->text('body')->nullable(); $table->text('body_oportunidad')->nullable(); $table->boolean('disable_integration_id_crm_prefix')->default(false);
            $table->string('crm_id_prefix')->nullable(); $table->string('public_key')->nullable(); $table->timestamps();
        });
        Schema::create('qualification', function (Blueprint $table) {$table->id(); $table->string('name')->nullable(); $table->timestamps();});
        Schema::create('crm_state', function (Blueprint $table) {$table->string('id', 255)->primary(); $table->string('name'); $table->unsignedBigInteger('qualification'); $table->timestamps();});
        Schema::create('freshworks_opportunity_stages', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('integration_id'); $table->string('pipeline_id'); $table->string('stage_id'); $table->string('crm_state_id'); $table->string('pipeline_name'); $table->string('stage_name'); $table->timestamps();
            $table->unique(['integration_id', 'pipeline_id', 'stage_id']);
        });
        Schema::create('integration_variables', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('integration_id'); $table->string('name', 80); $table->text('value'); $table->string('type', 30)->default('text');
            $table->unsignedInteger('order')->nullable(); $table->boolean('active')->default(true); $table->timestamps(); $table->unique(['integration_id', 'name']);
        });
        Schema::create('integration_variable_mappings', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('integration_id'); $table->string('target_variable'); $table->string('lead_field'); $table->string('expected_value');
            $table->text('mapped_value')->nullable(); $table->unsignedInteger('order')->nullable(); $table->boolean('active')->default(true); $table->timestamps();
        });
        Schema::create('integration_variable_conditions', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('integration_id'); $table->string('target_variable', 80); $table->string('source_type', 20)->default('lead');
            $table->string('source_key', 120); $table->string('operator', 50); $table->text('comparison_value')->nullable(); $table->text('result_value')->nullable();
            $table->string('result_type', 30)->default('text'); $table->unsignedInteger('order')->nullable(); $table->boolean('active')->default(true); $table->timestamps();
        });
        Schema::create('leads', function (Blueprint $table) {
            $table->id(); $table->string('name')->nullable(); $table->string('last_name')->nullable(); $table->string('phone')->nullable(); $table->string('email')->nullable();
            $table->string('city')->nullable(); $table->string('campaign_origin')->nullable(); $table->string('crm_id')->nullable(); $table->string('crm_id_oportunidad')->nullable(); $table->string('crm_state')->nullable(); $table->decimal('value', 12, 2)->nullable(); $table->timestamps();
        });
    }

    private function integration(): Integration
    {
        return Integration::create([
            'integrationtype_id' => Integrationtype::firstOrCreate(['name' => 'Freshworks-Oportunidad'])->id,
            'url' => 'https://crmmassymotorscolombia.myfreshworks.com', 'tokent' => 'fresh-token',
            'body' => '{"first_name":"{{lead->name}}","last_name":"{{lead->last_name}}","mobile_number":"{{lead->phone}}"}',
            'body_oportunidad' => '{"name":"{{lead->name}}","amount":"{{lead->value}}","deal_pipeline_id":28000108410,"deal_stage_id":28000109710,"probability":10}',
            'disable_integration_id_crm_prefix' => true, 'crm_id_prefix' => 'fresh-manual', 'public_key' => 'fresh-public-key',
        ]);
    }

    public function test_creates_a_missing_contact_by_mobile_number_and_creates_an_associated_deal(): void
    {
        $integration = $this->integration();
        $lead = Lead::create(['name' => 'Ana', 'last_name' => 'Diaz', 'phone' => '+57 300 555 0011', 'email' => 'ana@example.test', 'value' => 1000000]);
        Http::fake([
            '*/crm/sales/api/filtered_search/contact' => Http::response(['contacts' => []], 200),
            '*/crm/sales/api/contacts' => Http::response(['contact' => ['id' => 28193306975]], 201),
            '*/crm/sales/api/deals' => Http::response(['deal' => ['id' => 8001]], 201),
        ]);

        $response = app(FreshworksOpportunityIntegrationService::class)->sendToFreshworksOpportunity($lead, $integration);

        $this->assertTrue($response->successful());
        $this->assertSame('fresh-manual-28193306975', $lead->fresh()->crm_id);
        $this->assertSame('fresh-manual-8001', $lead->fresh()->crm_id_oportunidad);
        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://crmmassymotorscolombia.myfreshworks.com/crm/sales/api/filtered_search/contact'
            && data_get($request->data(), 'filter_rule.0.attribute') === 'mobile_number'
            && data_get($request->data(), 'filter_rule.0.value') === '+573005550011');
        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://crmmassymotorscolombia.myfreshworks.com/crm/sales/api/contacts'
            && data_get($request->data(), 'contact.mobile_number') === '+573005550011');
        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://crmmassymotorscolombia.myfreshworks.com/crm/sales/api/deals'
            && data_get($request->data(), 'deal.contacts_added_list.0') === 28193306975
            && data_get($request->data(), 'deal.deal_stage_id') === 28000109710);
    }

    public function test_it_fails_before_http_when_the_lead_has_no_valid_mobile_number(): void
    {
        Http::preventStrayRequests();
        $this->expectExceptionMessage('mobile_number válido');
        app(FreshworksOpportunityIntegrationService::class)->sendToFreshworksOpportunity(Lead::create(['phone' => '123']), $this->integration());
    }

    public function test_reuses_an_existing_contact_without_updating_it_and_creates_the_deal(): void
    {
        $integration = $this->integration();
        $lead = Lead::create(['name' => 'Ana', 'last_name' => 'Diaz', 'phone' => '3005550011', 'value' => 1000000]);
        Http::fake([
            '*/crm/sales/api/filtered_search/contact' => Http::response(['contacts' => [['id' => 28193306975]]], 200),
            '*/crm/sales/api/deals' => Http::response(['deal' => ['id' => 8001]], 201),
        ]);

        app(FreshworksOpportunityIntegrationService::class)->sendToFreshworksOpportunity($lead, $integration);

        $this->assertSame('fresh-manual-28193306975', $lead->fresh()->crm_id);
        Http::assertNotSent(fn (HttpRequest $request) => $request->url() === 'https://crmmassymotorscolombia.myfreshworks.com/crm/sales/api/contacts');
        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://crmmassymotorscolombia.myfreshworks.com/crm/sales/api/deals'
            && data_get($request->data(), 'deal.contacts_added_list.0') === 28193306975);
    }

    public function test_it_resolves_reusable_variables_conditions_and_mappings_in_both_payloads(): void
    {
        $integration = $this->integration();
        $integration->update([
            'body' => '{"first_name":"{{contact_name}}","last_name":"{{lead->last_name}}","mobile_number":"{{lead->phone}}"}',
            'body_oportunidad' => '{"name":"{{deal_name}}","amount":"{{lead->city}}","deal_pipeline_id":28000108410,"deal_stage_id":28000109710}',
        ]);
        IntegrationVariable::create([
            'integration_id' => $integration->id, 'name' => 'contact_name', 'value' => 'Cliente {{lead->name}}', 'type' => 'text', 'order' => 0, 'active' => true,
        ]);
        IntegrationVariable::create([
            'integration_id' => $integration->id, 'name' => 'deal_name', 'value' => 'Oportunidad sin clasificar', 'type' => 'text', 'order' => 1, 'active' => true,
        ]);
        IntegrationVariableCondition::create([
            'integration_id' => $integration->id, 'target_variable' => 'deal_name', 'source_type' => 'lead', 'source_key' => 'city', 'operator' => 'equals',
            'comparison_value' => 'Bogota', 'result_value' => 'Oportunidad {{lead->name}}', 'result_type' => 'text', 'order' => 0, 'active' => true,
        ]);
        IntegrationVariableMapping::create([
            'integration_id' => $integration->id, 'target_variable' => 'amount', 'lead_field' => 'city', 'expected_value' => 'Bogota',
            'mapped_value' => '1250000', 'order' => 0, 'active' => true,
        ]);
        $lead = Lead::create(['name' => 'Ana', 'last_name' => 'Diaz', 'phone' => '3005550011', 'city' => 'Bogota']);
        Http::fake([
            '*/crm/sales/api/filtered_search/contact' => Http::response(['contacts' => []], 200),
            '*/crm/sales/api/contacts' => Http::response(['contact' => ['id' => 28193306975]], 201),
            '*/crm/sales/api/deals' => Http::response(['deal' => ['id' => 8001]], 201),
        ]);

        app(FreshworksOpportunityIntegrationService::class)->sendToFreshworksOpportunity($lead, $integration);

        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://crmmassymotorscolombia.myfreshworks.com/crm/sales/api/contacts'
            && data_get($request->data(), 'contact.first_name') === 'Cliente Ana'
            && data_get($request->data(), 'contact.mobile_number') === '3005550011');
        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://crmmassymotorscolombia.myfreshworks.com/crm/sales/api/deals'
            && data_get($request->data(), 'deal.name') === 'Oportunidad Ana'
            && data_get($request->data(), 'deal.amount') === '1250000'
            && data_get($request->data(), 'deal.contacts_added_list.0') === 28193306975);
    }

    public function test_sync_and_webhook_use_the_prefixed_stage_and_deal_ids(): void
    {
        DB::table('qualification')->insert(['id' => 7, 'name' => 'Inicial', 'created_at' => now(), 'updated_at' => now()]);
        $integration = $this->integration();
        Http::fake([
            '*/selector/deal_pipelines' => Http::response(['deal_pipelines' => [['id' => 12, 'name' => 'Ventas']]]),
            '*/selector/deal_pipelines/12/deal_stages' => Http::response(['deal_stages' => [['id' => 34, 'name' => 'Calificado']]]),
        ]);
        $result = app(FreshworksOpportunityPipelineSyncService::class)->syncCrmStates($integration, '12');
        $this->assertSame(['created' => 1, 'updated' => 0], $result);
        $lead = Lead::create(['crm_id_oportunidad' => 'fresh-manual-500', 'crm_state' => 'old', 'value' => 1]);
        $history = Mockery::mock(LeadFunnelHistoryService::class);
        $history->shouldReceive('recordIfFunnelChanged')->once();

        $response = app(LeadCrmStateController::class)->update(Request::create('/', 'POST', [
            'deal_id' => '500', 'deal_pipeline_id' => '12', 'deal_stage_id' => '34', 'deal_amount' => '1.000.000,50',
        ]), 'fresh-public-key', $history);

        $this->assertSame(200, $response->status());
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'crm_state' => 'fresh-manual-34', 'value' => 1000000.50]);
    }

    public function test_the_existing_job_contract_resolves_the_additive_dispatcher(): void
    {
        $this->assertInstanceOf(FreshworksOpportunityAwareIntegrationService::class, app(IntegrationService::class));
    }
}
