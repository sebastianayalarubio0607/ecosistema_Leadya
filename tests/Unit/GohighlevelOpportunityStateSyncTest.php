<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\LeadCrmStateController;
use App\Http\Services\Integration\GohighlevelPipelineSyncService;
use App\Http\Services\Lead\LeadFunnelHistoryService;
use App\Models\CrmState;
use App\Models\Integration;
use App\Models\Integrationtype;
use App\Models\Lead;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class GohighlevelOpportunityStateSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Each test uses an in-memory SQLite database. The application's MySQL database is never reset.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('integrationtypes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('integrationtype_id');
            $table->text('tokent')->nullable();
            $table->string('location_id', 100)->nullable();
            $table->boolean('disable_integration_id_crm_prefix')->default(false);
            $table->string('crm_id_prefix')->nullable();
            $table->string('public_key')->nullable();
            $table->timestamps();
        });
        Schema::create('qualification', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });
        Schema::create('crm_state', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('name');
            $table->unsignedBigInteger('qualification')->nullable();
            $table->timestamps();
        });
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_origin')->nullable();
            $table->string('crm_id_oportunidad')->nullable();
            $table->string('crm_state')->nullable();
            $table->decimal('value', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    private function integration(string $prefix = 'integration-44'): Integration
    {
        return Integration::create([
            'integrationtype_id' => Integrationtype::firstOrCreate(['name' => 'GoHighLevel-Oportunidad'])->id,
            'tokent' => 'Bearer test-token',
            'location_id' => 'location-1',
            'disable_integration_id_crm_prefix' => true,
            'crm_id_prefix' => $prefix,
            'public_key' => 'public-key-'.$prefix,
        ]);
    }

    public function test_sync_creates_and_updates_only_the_selected_pipeline_states(): void
    {
        DB::table('qualification')->insert(['id' => 7, 'name' => 'Inicial', 'created_at' => now(), 'updated_at' => now()]);
        $integration = $this->integration('manual-prefix');
        CrmState::create(['id' => 'manual-prefix-stage-1', 'name' => 'Nombre anterior', 'qualification' => 99]);
        Http::fake(['services.leadconnectorhq.com/*' => Http::response(['pipelines' => [
            ['id' => 'pipeline-a', 'name' => 'Ventas', 'stages' => [
                ['id' => 'stage-1', 'name' => 'Nuevo', 'position' => 0],
                ['id' => 'stage-2', 'name' => 'Calificado', 'position' => 1],
            ]],
            ['id' => 'pipeline-b', 'name' => 'Soporte', 'stages' => [
                ['id' => 'stage-other', 'name' => 'Abierto', 'position' => 0],
            ]],
        ]])]);

        $result = app(GohighlevelPipelineSyncService::class)->syncCrmStates($integration, 'pipeline-a');

        $this->assertSame(['created' => 1, 'updated' => 1], $result);
        $this->assertDatabaseHas('crm_state', ['id' => 'manual-prefix-stage-1', 'name' => 'Nuevo | Ventas', 'qualification' => 99]);
        $this->assertDatabaseHas('crm_state', ['id' => 'manual-prefix-stage-2', 'name' => 'Calificado | Ventas', 'qualification' => 7]);
        $this->assertDatabaseMissing('crm_state', ['id' => 'manual-prefix-stage-other']);
        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://services.leadconnectorhq.com/opportunities/pipelines?locationId=location-1'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request->hasHeader('Version', 'v3'));
    }

    public function test_webhook_updates_the_resolved_state_and_a_numeric_value_by_opportunity_id(): void
    {
        $integration = $this->integration('manual-prefix');
        CrmState::create(['id' => 'manual-prefix-stage-uuid', 'name' => 'Calificado | Ventas', 'qualification' => 1]);
        $lead = Lead::create(['crm_id_oportunidad' => 'manual-prefix-opportunity-uuid', 'crm_state' => 'previous', 'value' => 10]);
        $history = Mockery::mock(LeadFunnelHistoryService::class);
        $history->shouldReceive('recordIfFunnelChanged')->once();

        $response = app(LeadCrmStateController::class)->update(Request::create('/', 'POST', [
            'crm_id' => 'contact-uuid',
            'crm_id_oportunidad' => 'opportunity-uuid',
            'value' => '1.234,50',
            'pipeline_name' => 'Ventas',
            'stage_name' => 'Calificado',
        ]), (string) $integration->public_key, $history);

        $this->assertSame(200, $response->status());
        $this->assertSame(['updated' => 1, 'value_updated' => 1], array_intersect_key($response->getData(true), array_flip(['updated', 'value_updated'])));
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'crm_state' => 'manual-prefix-stage-uuid', 'value' => 1234.50]);
    }

    public function test_webhook_leaves_state_and_value_unchanged_when_the_state_is_not_resolved(): void
    {
        $integration = $this->integration('manual-prefix');
        $lead = Lead::create(['crm_id_oportunidad' => 'manual-prefix-opportunity-uuid', 'crm_state' => 'previous', 'value' => 10]);
        $history = Mockery::mock(LeadFunnelHistoryService::class);
        $history->shouldNotReceive('recordIfFunnelChanged');

        $response = app(LeadCrmStateController::class)->update(Request::create('/', 'POST', [
            'crm_id_oportunidad' => 'opportunity-uuid',
            'value' => '999',
            'pipeline_name' => 'Ventas',
            'stage_name' => 'No sincronizado',
        ]), (string) $integration->public_key, $history);

        $this->assertSame(200, $response->status());
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'crm_state' => 'previous', 'value' => 10]);
    }

    public function test_webhook_updates_state_but_ignores_an_invalid_value(): void
    {
        $integration = $this->integration('manual-prefix');
        CrmState::create(['id' => 'manual-prefix-stage-uuid', 'name' => 'Calificado | Ventas', 'qualification' => 1]);
        $lead = Lead::create(['crm_id_oportunidad' => 'manual-prefix-opportunity-uuid', 'crm_state' => 'previous', 'value' => 10]);
        $history = Mockery::mock(LeadFunnelHistoryService::class);
        $history->shouldReceive('recordIfFunnelChanged')->once();

        app(LeadCrmStateController::class)->update(Request::create('/', 'POST', [
            'crm_id_oportunidad' => 'opportunity-uuid',
            'value' => 'sin valor numérico',
            'pipeline_name' => 'Ventas',
            'stage_name' => 'Calificado',
        ]), (string) $integration->public_key, $history);

        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'crm_state' => 'manual-prefix-stage-uuid', 'value' => 10]);
    }
}
