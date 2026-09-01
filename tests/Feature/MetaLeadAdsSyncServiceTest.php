<?php

namespace Tests\Feature;

use App\Http\Services\Integration\IntegrationService;
use App\Http\Services\Lead\LeadFunnelHistoryService;
use App\Http\Services\Lead\LeadService;
use App\Http\Services\Meta\MetaGraphService;
use App\Http\Services\Meta\MetaLeadAdsSyncService;
use App\Jobs\SendLeadToFacebook;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\MetaForm;
use App\Models\MetaFormFieldMapping;
use App\Models\MetaPage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class MetaLeadAdsSyncServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        foreach ($this->migrations() as $migrationPath) {
            $migration = require database_path('migrations/'.$migrationPath);
            $migration->up();
        }

        Queue::fake();
    }

    protected function tearDown(): void
    {
        foreach ([
            'meta_form_field_mappings',
            'meta_forms',
            'meta_pages',
            'meta_access_tokens',
            'leads',
            'crm_state',
            'qualification',
            'integrations',
            'integrationtypes',
            'customers',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_it_normalizes_prefixed_meta_ids_and_field_values_without_breaking_mapped_fields(): void
    {
        [$customer, $page, $form] = $this->createActiveMetaForm();

        MetaFormFieldMapping::query()->create([
            'meta_form_id' => $form->id,
            'meta_field_name' => 'full_name',
            'lead_field_name' => 'name',
            'is_required' => true,
            'is_active' => true,
        ]);
        MetaFormFieldMapping::query()->create([
            'meta_form_id' => $form->id,
            'meta_field_name' => 'phone_number',
            'lead_field_name' => 'phone',
            'is_required' => true,
            'is_active' => true,
        ]);
        MetaFormFieldMapping::query()->create([
            'meta_form_id' => $form->id,
            'meta_field_name' => 'document',
            'lead_field_name' => 'reference',
            'is_required' => false,
            'is_active' => true,
        ]);
        MetaFormFieldMapping::query()->create([
            'meta_form_id' => $form->id,
            'meta_field_name' => 'origin',
            'lead_field_name' => 'campaign_origin',
            'is_required' => false,
            'is_active' => true,
        ]);
        MetaFormFieldMapping::query()->create([
            'meta_form_id' => $form->id,
            'meta_field_name' => 'platform_field',
            'lead_field_name' => 'plataforma',
            'is_required' => false,
            'is_active' => true,
        ]);

        $service = $this->serviceReturningMetaLeads([
            [
                'id' => 'l:3365649860301876',
                'created_time' => '2026-08-27T10:00:00-05:00',
                'ad_id' => 'ag:120254068070430368',
                'form_id' => 'f:1378624450914687',
                'campaign_id' => 'cmp:987654321',
                'field_data' => [
                    ['name' => 'full_name', 'values' => ['Laura Meta']],
                    ['name' => 'p:phone_number', 'values' => ['p:+573233600190']],
                    ['name' => 'document', 'values' => ['ref:CC-987']],
                    ['name' => 'origin', 'values' => ['mapped-origin']],
                    ['name' => 'platform_field', 'values' => ['Mapped Platform']],
                    ['name' => 'extra_field', 'values' => ['extra:Conservar valor']],
                ],
            ],
        ], $form);

        $result = $service->syncLeads(
            $form,
            Carbon::parse('2026-08-27 09:00:00'),
            Carbon::parse('2026-08-27 11:00:00'),
        );

        $this->assertSame(1, $result['leads_created']);
        $this->assertSame(0, $result['leads_updated']);

        $lead = Lead::query()->firstOrFail();

        $this->assertSame($customer->id, $lead->customer_id);
        $this->assertSame($page->id, $lead->meta_page_id);
        $this->assertSame($form->id, $lead->meta_form_id);
        $this->assertSame('3365649860301876', $lead->meta_lead_id);
        $this->assertSame('120254068070430368', $lead->meta_id_ad);
        $this->assertSame('Laura Meta', $lead->name);
        $this->assertSame('573233600190', $lead->phone);
        $this->assertSame('CC-987', $lead->reference);
        $this->assertSame('mapped-origin', $lead->campaign_origin);
        $this->assertSame('Mapped Platform', $lead->plataforma);
        $this->assertSame(['extra_field' => 'Conservar valor'], $lead->fields_custom);
        $this->assertSame('3365649860301876', data_get($lead->meta_payload, 'id'));
        $this->assertSame('120254068070430368', data_get($lead->meta_payload, 'ad_id'));
        $this->assertSame('1378624450914687', data_get($lead->meta_payload, 'form_id'));
        $this->assertSame('987654321', data_get($lead->meta_payload, 'campaign_id'));

        Queue::assertPushed(SendLeadToFacebook::class, function (SendLeadToFacebook $job) use ($lead, $customer): bool {
            return $job->leadId === $lead->id
                && $job->customerId === $customer->id
                && $job->eventNameOverride === 'Lead';
        });
    }

    public function test_it_uses_default_meta_origin_and_platform_when_they_are_not_mapped(): void
    {
        [, , $form] = $this->createActiveMetaForm();

        MetaFormFieldMapping::query()->create([
            'meta_form_id' => $form->id,
            'meta_field_name' => 'full_name',
            'lead_field_name' => 'name',
            'is_required' => true,
            'is_active' => true,
        ]);
        MetaFormFieldMapping::query()->create([
            'meta_form_id' => $form->id,
            'meta_field_name' => 'phone_number',
            'lead_field_name' => 'phone',
            'is_required' => true,
            'is_active' => true,
        ]);

        $service = $this->serviceReturningMetaLeads([
            [
                'id' => '3365649860301877',
                'created_time' => '2026-08-27T10:00:00-05:00',
                'ad_id' => '120254068070430369',
                'form_id' => '1378624450914687',
                'campaign_id' => '999999999',
                'platform' => 'instagram',
                'field_data' => [
                    ['name' => 'full_name', 'values' => ['Default Meta']],
                    ['name' => 'phone_number', 'values' => ['+57 300 111 2233']],
                ],
            ],
        ], $form);

        $service->syncLeads(
            $form,
            Carbon::parse('2026-08-27 09:00:00'),
            Carbon::parse('2026-08-27 11:00:00'),
        );

        $lead = Lead::query()->firstOrFail();

        $this->assertSame('meta', $lead->campaign_origin);
        $this->assertSame('Formulario instantáneo Meta', $lead->plataforma);
        $this->assertNull($lead->reference);
        $this->assertSame('573001112233', $lead->phone);
    }

    private function createActiveMetaForm(): array
    {
        $customer = Customer::query()->create([
            'name' => 'Cliente Meta Forms',
            'status' => true,
        ]);

        $page = MetaPage::withoutEvents(fn () => MetaPage::query()->create([
            'customer_id' => $customer->id,
            'meta_page_id' => '120254068070430368',
            'name' => 'Pagina Meta',
            'page_access_token' => 'page-token',
            'status' => true,
        ]));

        $form = MetaForm::query()->create([
            'meta_page_id' => $page->id,
            'meta_form_id' => '1378624450914687',
            'name' => 'Formulario Prueba',
            'status' => true,
            'meta_status' => 'ACTIVE',
            'raw_payload' => [],
        ]);

        return [$customer, $page, $form];
    }

    private function serviceReturningMetaLeads(array $leads, MetaForm $form): MetaLeadAdsSyncService
    {
        $graphService = Mockery::mock(MetaGraphService::class);
        $graphService->shouldReceive('paginatedGet')
            ->once()
            ->with($form->meta_form_id.'/leads', Mockery::on(function (array $query): bool {
                return ($query['access_token'] ?? null) === 'page-token'
                    && ($query['fields'] ?? null) === 'id,created_time,ad_id,form_id,field_data,campaign_id'
                    && ($query['limit'] ?? null) === 500;
            }))
            ->andReturn($leads);

        $integrationService = Mockery::mock(IntegrationService::class);
        $integrationService->shouldReceive('getActiveIntegrations')
            ->once()
            ->andReturn(collect());

        $leadFunnelHistoryService = Mockery::mock(LeadFunnelHistoryService::class);
        $leadFunnelHistoryService->shouldReceive('recordInitialLead')
            ->once()
            ->with(Mockery::type(Lead::class))
            ->andReturn(null);

        return new MetaLeadAdsSyncService(
            $graphService,
            $integrationService,
            $leadFunnelHistoryService,
            app(LeadService::class),
        );
    }

    private function migrations(): array
    {
        return [
            '2025_04_25_210705_create_customers_table.php',
            '2025_04_25_205118_create_integrationtypes_table.php',
            '2025_04_25_210715_create_integrations_table.php',
            '2025_04_25_210757_create_leads_table.php',
            '2026_01_09_090804_create_qualification_table.php',
            '2026_01_09_090958_create_crm_state_table.php',
            '2026_01_09_091030_add_crm_fields_to_leads_table.php',
            '2026_02_10_094014_add_meta_fields_and_resize_page_url_agent_in_leads_table.php',
            '2026_03_18_090000_create_meta_access_tokens_table.php',
            '2026_03_18_090100_create_meta_pages_table.php',
            '2026_03_18_090200_create_meta_forms_table.php',
            '2026_03_18_090300_create_meta_form_field_mappings_table.php',
            '2026_03_18_120000_adjust_meta_access_tokens_and_mappings.php',
            '2026_03_18_090400_add_meta_lead_tracking_to_leads_table.php',
        ];
    }
}
