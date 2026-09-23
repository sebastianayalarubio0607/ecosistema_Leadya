<?php

namespace Tests\Unit;

use App\Http\Services\Integration\Concerns\ResolvesIntegrationVariableMappings;
use App\Http\Services\Integration\LeadIntegrationContextService;
use App\Models\Integration;
use App\Models\Lead;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadIntegrationContextVariableResolver
{
    use ResolvesIntegrationVariableMappings;

    public function value(Lead $lead, string $name): mixed
    {
        return $this->resolveIntegrationVariableValue(new Integration, $lead, $name);
    }
}

class LeadIntegrationContextServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Isolated in-memory database: no MySQL data is modified by these tests.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('campaign_origin')->nullable();
            $table->string('plataforma')->nullable();
            $table->string('google_campaign_id')->nullable();
            $table->string('gad_campaignid')->nullable();
            $table->string('google_ad_id')->nullable();
            $table->string('g_ad')->nullable();
            $table->string('google_adgroup_id')->nullable();
            $table->string('meta_id_ad')->nullable();
            $table->json('meta_payload')->nullable();
            $table->timestamps();
        });
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('origins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('code')->unique();
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('platforms', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('google_ads_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('google_campaign_id');
            $table->string('campaign_name')->nullable();
            $table->date('report_date')->nullable();
            $table->timestamps();
        });
        Schema::create('google_ads_ads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('google_ad_id');
            $table->string('google_ad_group_id')->nullable();
            $table->string('ad_group_name')->nullable();
            $table->string('google_campaign_id')->nullable();
            $table->string('campaign_name')->nullable();
            $table->json('raw_payload')->nullable();
            $table->date('report_date')->nullable();
            $table->timestamps();
        });
        Schema::create('google_ads_ad_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('google_ad_group_id');
            $table->string('ad_group_name')->nullable();
            $table->string('google_campaign_id')->nullable();
            $table->string('campaign_name')->nullable();
            $table->date('report_date')->nullable();
            $table->timestamps();
        });
        Schema::create('meta_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('meta_campaign_id')->unique();
            $table->string('name')->nullable();
            $table->timestamps();
        });
        Schema::create('meta_ad_sets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meta_campaign_id');
            $table->string('meta_ad_set_id')->unique();
            $table->string('name')->nullable();
            $table->timestamps();
        });
        Schema::create('meta_ads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meta_ad_set_id');
            $table->string('meta_ad_id')->unique();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    public function test_it_resolves_the_latest_local_google_campaign_and_attribution_names(): void
    {
        DB::table('sources')->insert(['id' => 1, 'code' => 'paid', 'name' => 'Medios pagos', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('origins')->insert(['source_id' => 1, 'code' => 'google', 'name' => 'Google Ads', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('platforms')->insert(['code' => 'google_ads', 'name' => 'Google Ads', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('google_ads_campaigns')->insert([
            ['customer_id' => 9, 'google_campaign_id' => 'g-1', 'campaign_name' => 'Nombre anterior', 'report_date' => '2026-09-20', 'created_at' => now(), 'updated_at' => now()],
            ['customer_id' => 9, 'google_campaign_id' => 'g-1', 'campaign_name' => 'Nombre actualizado', 'report_date' => '2026-09-21', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $lead = Lead::create(['customer_id' => 9, 'google_campaign_id' => 'g-1', 'campaign_origin' => 'google', 'plataforma' => 'google_ads']);

        $context = app(LeadIntegrationContextService::class)->context($lead);

        $this->assertSame('Nombre actualizado', $context['campaign_name']);
        $this->assertSame('Google Ads', $context['campaign_origin_name']);
        $this->assertSame('Medios pagos', $context['source_name']);
        $this->assertSame('Google Ads', $context['platform_name']);
        $this->assertSame('g-1', data_get($context, 'attribution_relation.campaign.id'));
        $this->assertTrue(data_get($context, 'attribution_relation.campaign.resolved'));
        $this->assertSame('Nombre actualizado', (new LeadIntegrationContextVariableResolver)->value($lead, 'campaign_name'));
    }

    public function test_it_resolves_meta_through_the_local_ad_hierarchy_and_preserves_unresolved_lead_data(): void
    {
        DB::table('meta_campaigns')->insert(['id' => 10, 'meta_campaign_id' => 'm-1', 'name' => 'Meta vigente', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('meta_ad_sets')->insert(['id' => 11, 'meta_campaign_id' => 10, 'meta_ad_set_id' => 'set-1', 'name' => 'Conjunto', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('meta_ads')->insert(['meta_ad_set_id' => 11, 'meta_ad_id' => 'ad-1', 'name' => 'Anuncio', 'created_at' => now(), 'updated_at' => now()]);
        $lead = Lead::create(['meta_id_ad' => 'ad-1', 'campaign_origin' => 'origen_no_catalogado', 'plataforma' => 'medio_no_catalogado']);

        $context = app(LeadIntegrationContextService::class)->context($lead);

        $this->assertSame('meta', $context['campaign_provider']);
        $this->assertSame('m-1', $context['campaign_id']);
        $this->assertSame('Meta vigente', $context['campaign_name']);
        $this->assertSame('origen_no_catalogado', $context['origin_name']);
        $this->assertSame('medio_no_catalogado', $context['platform_name']);
        $this->assertFalse(data_get($context, 'origin_relation.resolved'));
        $this->assertFalse(data_get($context, 'platform_relation.resolved'));
        $this->assertSame('', $context['source_name']);

        $unresolvedLead = Lead::create(['customer_id' => 9, 'google_campaign_id' => 'g-no-catalogo']);
        $unresolved = app(LeadIntegrationContextService::class)->context($unresolvedLead);

        $this->assertSame('g-no-catalogo', $unresolved['campaign_name']);
        $this->assertFalse(data_get($unresolved, 'campaign_relation.resolved'));
    }

    public function test_it_resolves_google_campaign_from_ad_group_and_exposes_advertising_name_variables(): void
    {
        DB::table('google_ads_campaigns')->insert([
            'customer_id' => 9,
            'google_campaign_id' => 'campaign-1',
            'campaign_name' => 'Campaña actualizada',
            'report_date' => '2026-09-21',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('google_ads_ad_groups')->insert([
            'customer_id' => 9,
            'google_ad_group_id' => 'group-1',
            'ad_group_name' => 'Grupo actualizado',
            'google_campaign_id' => 'campaign-1',
            'campaign_name' => 'Campaña desde grupo',
            'report_date' => '2026-09-21',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('google_ads_ads')->insert([
            'customer_id' => 9,
            'google_ad_id' => 'ad-1',
            'google_ad_group_id' => 'group-1',
            'ad_group_name' => 'Grupo anterior',
            'google_campaign_id' => 'campaign-1',
            'campaign_name' => 'Campaña anterior',
            'raw_payload' => json_encode(['adGroupAd' => ['ad' => ['name' => 'Anuncio actualizado']]]),
            'report_date' => '2026-09-20',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $lead = Lead::create(['customer_id' => 9, 'google_ad_id' => 'ad-1', 'google_adgroup_id' => 'group-1']);

        $context = app(LeadIntegrationContextService::class)->context($lead);

        $this->assertSame('Campaña actualizada', $context['campaign_name']);
        $this->assertSame('Grupo actualizado', $context['ad_group_name']);
        $this->assertSame('Anuncio actualizado', $context['ad_name']);
        $this->assertSame('campaign-1', $context['campaign_id']);
        $this->assertSame('group-1', $context['ad_group_id']);
        $this->assertSame('ad-1', $context['ad_id']);
        $this->assertSame('Grupo actualizado', (new LeadIntegrationContextVariableResolver)->value($lead, 'ad_group_name'));
        $this->assertSame('Anuncio actualizado', (new LeadIntegrationContextVariableResolver)->value($lead, 'ad_name'));
    }

    public function test_it_uses_the_lead_ad_identifier_when_the_local_hierarchy_is_not_available(): void
    {
        $lead = Lead::create([
            'customer_id' => 9,
            'google_ad_id' => 'ad-not-synchronized',
            'google_adgroup_id' => 'group-not-synchronized',
            'google_campaign_id' => 'campaign-not-synchronized',
        ]);

        $context = app(LeadIntegrationContextService::class)->context($lead);

        $this->assertSame('ad-not-synchronized', $context['campaign_name']);
        $this->assertSame('ad-not-synchronized', $context['ad_name']);
        $this->assertSame('group-not-synchronized', $context['ad_group_name']);
        $this->assertFalse(data_get($context, 'campaign_relation.resolved'));
    }

    public function test_it_uses_each_entity_id_when_a_local_name_is_blank_or_a_null_placeholder(): void
    {
        DB::table('google_ads_campaigns')->insert([
            'customer_id' => 9,
            'google_campaign_id' => 'campaign-empty',
            'campaign_name' => 'null',
            'report_date' => '2026-09-21',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('google_ads_ad_groups')->insert([
            'customer_id' => 9,
            'google_ad_group_id' => 'group-empty',
            'ad_group_name' => '   ',
            'google_campaign_id' => 'campaign-empty',
            'campaign_name' => ' ',
            'report_date' => '2026-09-21',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('google_ads_ads')->insert([
            'customer_id' => 9,
            'google_ad_id' => 'ad-empty',
            'google_ad_group_id' => 'group-empty',
            'ad_group_name' => 'undefined',
            'google_campaign_id' => 'campaign-empty',
            'campaign_name' => '',
            'raw_payload' => json_encode(['adGroupAd' => ['ad' => ['name' => '  ']]]),
            'report_date' => '2026-09-21',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $lead = Lead::create(['customer_id' => 9, 'google_ad_id' => 'ad-empty']);

        $context = app(LeadIntegrationContextService::class)->context($lead);

        $this->assertSame('campaign-empty', $context['campaign_name']);
        $this->assertSame('group-empty', $context['ad_group_name']);
        $this->assertSame('ad-empty', $context['ad_name']);
        $this->assertFalse(data_get($context, 'campaign_relation.resolved'));
        $this->assertFalse(data_get($context, 'ad_group_relation.resolved'));
        $this->assertFalse(data_get($context, 'ad_relation.resolved'));
    }
}
