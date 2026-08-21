<?php

namespace Tests\Unit;

use App\Http\Services\Lead\LeadFunnelHistoryService;
use App\Models\Lead;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadFunnelHistoryServiceTest extends TestCase
{
    private array $originalDatabaseConfig;

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
        DB::reconnect('sqlite');

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        config(['database' => $this->originalDatabaseConfig]);

        parent::tearDown();
    }

    public function test_initial_history_for_new_lead_with_crm_state_uses_the_state_funnel(): void
    {
        DB::table('funnels')->insert([
            ['id' => 7, 'name' => 'Oportunidades', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('qualification')->insert([
            ['id' => 1, 'name' => 'Calificado', 'funnel_id' => 7, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('crm_state')->insert([
            ['id' => 'crm-1', 'name' => 'Calificado', 'qualification' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $lead = Lead::query()->create(['crm_state' => 'crm-1']);

        $history = app(LeadFunnelHistoryService::class)->recordInitialLead($lead);

        $this->assertSame(7, (int) $history->funnel_id);
        $this->assertSame(0, DB::table('lead_funnel_histories')->where('lead_id', $lead->id)->where('funnel_id', 5)->count());
        $this->assertSame(1, DB::table('lead_funnel_histories')->where('lead_id', $lead->id)->where('funnel_id', 7)->count());
    }

    public function test_initial_history_for_new_lead_without_crm_state_uses_leads_funnel_id_five(): void
    {
        $lead = Lead::query()->create(['crm_state' => null]);

        $history = app(LeadFunnelHistoryService::class)->recordInitialLead($lead);

        $this->assertSame(5, (int) $history->funnel_id);
        $this->assertSame('Leads', DB::table('funnels')->where('id', 5)->value('name'));
    }

    public function test_state_changes_still_record_the_resolved_funnel(): void
    {
        DB::table('funnels')->insert([
            ['id' => 5, 'name' => 'Leads', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 7, 'name' => 'Oportunidades', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('qualification')->insert([
            ['id' => 1, 'name' => 'Calificado', 'funnel_id' => 7, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('crm_state')->insert([
            ['id' => 'crm-1', 'name' => 'Calificado', 'qualification' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $lead = Lead::query()->create(['crm_state' => null]);
        $service = app(LeadFunnelHistoryService::class);

        $service->recordInitialLead($lead);
        $lead->crm_state = 'crm-1';
        $lead->save();
        $history = $service->recordIfFunnelChanged($lead);

        $this->assertSame(7, (int) $history->funnel_id);
        $this->assertSame(1, DB::table('lead_funnel_histories')->where('lead_id', $lead->id)->where('funnel_id', 5)->count());
        $this->assertSame(1, DB::table('lead_funnel_histories')->where('lead_id', $lead->id)->where('funnel_id', 7)->count());
    }

    public function test_unresolved_funnel_falls_back_to_leads_funnel_id_five(): void
    {
        $lead = Lead::query()->create(['crm_state' => null]);

        $history = app(LeadFunnelHistoryService::class)->recordIfFunnelChanged($lead);

        $this->assertSame(5, (int) $history->funnel_id);
        $this->assertSame('Leads', DB::table('funnels')->where('id', 5)->value('name'));
        $this->assertFalse(DB::table('funnels')->whereRaw('LOWER(TRIM(name)) = ?', ['lead'])->exists());
    }

    private function createSchema(): void
    {
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
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('crm_state')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_funnel_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('funnel_id');
            $table->timestamps();
        });
    }
}
