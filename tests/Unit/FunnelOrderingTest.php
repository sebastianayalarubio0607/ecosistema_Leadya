<?php

namespace Tests\Unit;

use App\Http\Controllers\Funnel\FunnelWebController;
use App\Models\Funnel;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FunnelOrderingTest extends TestCase
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

        Schema::create('funnels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('status')->default('active');
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
            $table->unsignedBigInteger('qualification');
            $table->unsignedBigInteger('meta_event_id')->nullable();
        });
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('crm_state');
        });
        Schema::create('lead_funnel_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('funnel_id');
            $table->timestamps();
        });
        $this->migration()->up();
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        config(['database' => $this->originalDatabaseConfig]);
        parent::tearDown();
    }

    public function test_migration_initializes_alphabetically_and_can_be_reversed_without_losing_data(): void
    {
        $migration = $this->migration();
        $migration->down();
        foreach ([3 => 'Venta', 1 => 'Contacto', 2 => 'Contacto'] as $id => $name) {
            DB::table('funnels')->insert(['id' => $id, 'name' => $name, 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01']);
        }
        $before = DB::table('funnels')->orderBy('id')->get()->toJson();

        $migration->up();

        $this->assertSame([1 => 5, 2 => 10, 3 => 15], DB::table('funnels')->orderBy('id')->pluck('orden', 'id')->all());
        $migration->down();
        $this->assertFalse(Schema::hasColumn('funnels', 'orden'));
        $this->assertSame($before, DB::table('funnels')->orderBy('id')->get()->toJson());
    }

    public function test_reorder_covers_all_pages_preserves_ties_and_changes_only_order(): void
    {
        $this->actingAs((new User)->forceFill(['id' => 1, 'name' => 'Test']));
        for ($id = 1; $id <= 18; $id++) {
            Funnel::query()->create([
                'name' => $id <= 2 ? 'Igual' : sprintf('Funnel %02d', $id),
                'orden' => $id <= 2 ? 2 : ($id === 18 ? null : $id * 11),
                'status' => $id % 2 ? 'active' : 'inactive',
                'description' => 'Conservar',
            ]);
        }
        DB::table('qualification')->insert(['id' => 1, 'name' => 'Calificado', 'funnel_id' => 2]);
        DB::table('crm_state')->insert(['id' => 'crm-1', 'qualification' => 1, 'meta_event_id' => 7]);
        DB::table('leads')->insert(['id' => 1, 'crm_state' => 'crm-1']);
        DB::table('lead_funnel_histories')->insert(['lead_id' => 1, 'funnel_id' => 2, 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01']);
        $before = $this->snapshotWithoutOrder();
        $sequence = Funnel::query()->inDisplayOrder()->pluck('id')->all();

        $this->post(route('funnels.reorder', ['q' => 'Igual', 'page' => 2]))
            ->assertRedirect(route('funnels.index'))->assertSessionHas('success');

        $this->assertSame($sequence, Funnel::query()->inDisplayOrder()->pluck('id')->all());
        $this->assertSame(range(5, 90, 5), Funnel::query()->inDisplayOrder()->pluck('orden')->all());
        $this->assertSame($before, $this->snapshotWithoutOrder());
        $once = DB::table('funnels')->orderBy('id')->get()->toJson();
        $this->post(route('funnels.reorder'))->assertRedirect(route('funnels.index'));
        $this->assertSame($once, DB::table('funnels')->orderBy('id')->get()->toJson());
    }

    public function test_reorder_is_atomic_when_a_write_fails(): void
    {
        Funnel::query()->create(['name' => 'Primero', 'orden' => 1]);
        Funnel::query()->create(['name' => 'Segundo', 'orden' => 2]);
        DB::unprepared("CREATE TRIGGER fail_second_order BEFORE UPDATE OF orden ON funnels WHEN OLD.id = 2 BEGIN SELECT RAISE(ABORT, 'simulated failure'); END");

        try {
            (new FunnelWebController)->reorder();
            $this->fail('Expected the simulated write failure.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertStringContainsString('simulated failure', $exception->getMessage());
            $this->assertSame([1, 2], DB::table('funnels')->orderBy('id')->pluck('orden')->all());
        }
    }

    public function test_reorder_requires_login(): void
    {
        $this->post(route('funnels.reorder'))->assertRedirect(route('login'));
    }

    public function test_empty_catalog_can_be_reordered(): void
    {
        $this->actingAs((new User)->forceFill(['id' => 1, 'name' => 'Test']));
        $this->post(route('funnels.reorder'))->assertRedirect(route('funnels.index'));
        $this->assertSame(0, Funnel::query()->count());
    }

    public function test_create_edit_and_list_use_order_and_keep_qualification_assignment(): void
    {
        $this->actingAs((new User)->forceFill(['id' => 1, 'name' => 'Test']));
        DB::table('qualification')->insert(['id' => 1, 'name' => 'Calificado']);
        $this->post(route('funnels.store'), [
            'name' => 'Venta', 'orden' => 12, 'status' => 'active', 'qualification_ids' => [1],
        ])->assertRedirect(route('funnels.index'));
        $funnel = Funnel::query()->firstOrFail();
        $this->assertSame(12, $funnel->orden);
        $this->assertSame($funnel->id, DB::table('qualification')->value('funnel_id'));
        $this->put(route('funnels.update', $funnel), [
            'name' => 'Venta', 'orden' => 2, 'status' => 'active', 'qualification_ids' => [1],
        ])->assertRedirect(route('funnels.index'));
        $this->assertSame(2, $funnel->fresh()->orden);
        $this->assertSame($funnel->id, DB::table('qualification')->value('funnel_id'));
        Funnel::query()->create(['name' => 'Antes alfabeticamente', 'orden' => 10]);
        Funnel::query()->create(['name' => 'Sin orden']);
        $controller = new FunnelWebController;
        $data = $controller->index(Request::create('/funnels'))->getData();
        $this->assertSame(['Venta', 'Antes alfabeticamente', 'Sin orden'], $data['funnels']->pluck('name')->all());
        $this->assertSame(15, $controller->create()->getData()['funnel']->orden);
    }

    public function test_invalid_orders_are_rejected_on_create_and_update(): void
    {
        $this->actingAs((new User)->forceFill(['id' => 1, 'name' => 'Test']));
        $funnel = Funnel::query()->create(['name' => 'Venta', 'orden' => 5]);
        foreach ([null, '', 0, -1, 1.5, 'texto', 2147483648] as $invalid) {
            $data = ['name' => 'Venta', 'status' => 'active', 'orden' => $invalid];
            $this->postJson(route('funnels.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('orden');
            $this->putJson(route('funnels.update', $funnel), $data)->assertUnprocessable()->assertJsonValidationErrors('orden');
        }
        $this->assertSame(1, Funnel::query()->count());
        $this->assertSame(5, $funnel->fresh()->orden);
    }

    public function test_funnel_pages_render_order_controls_and_values(): void
    {
        $this->withoutVite();
        $this->actingAs((new User)->forceFill(['id' => 1, 'name' => 'Test', 'email' => 'test@example.com']));
        $funnel = Funnel::query()->create(['name' => 'Venta', 'orden' => 12]);

        $this->get(route('funnels.index'))->assertOk()->assertSee('Ordenar valores')
            ->assertSee(route('funnels.reorder'), false)->assertSee('>12</td>', false);
        $this->get(route('funnels.create'))->assertOk()->assertSee('name="orden"', false)
            ->assertSee('value="17"', false);
        $this->get(route('funnels.edit', $funnel))->assertOk()->assertSee('name="orden"', false)
            ->assertSee('value="12"', false);
        $this->get(route('funnels.show', $funnel))->assertOk()->assertSee('Orden')
            ->assertSee('>12</div>', false);
    }

    private function migration(): \Illuminate\Database\Migrations\Migration
    {
        return require database_path('migrations/2026_09_22_010000_add_orden_to_funnels_table.php');
    }

    private function snapshotWithoutOrder(): array
    {
        $snapshot = ['funnels' => DB::table('funnels')->orderBy('id')
            ->get(['id', 'name', 'description', 'status', 'created_at', 'updated_at'])->toJson()];
        foreach (['qualification', 'crm_state', 'leads', 'lead_funnel_histories'] as $table) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $snapshot;
    }
}
