<?php

namespace Tests\Unit;

use App\Livewire\GohighlevelPipelineCatalog;
use App\Models\Integration;
use App\Models\Integrationtype;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GohighlevelPipelineCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Isolated test storage only; never migrate or reset the application's MySQL database.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('integrationtypes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('integrationtype_id');
            $table->text('tokent')->nullable();
            $table->text('body')->nullable();
            $table->text('body_oportunidad')->nullable();
            $table->timestamps();
        });
        $user = new User;
        $user->id = 1;
        $this->actingAs($user);
        Http::preventStrayRequests();
    }

    private function integration(string $type = 'GoHighLevel-Oportunidad', ?string $token = 'Bearer private-test-token'): Integration
    {
        return Integration::create([
            'integrationtype_id' => Integrationtype::create(['name' => $type])->id,
            'tokent' => $token,
            'body' => '{"locationId":"original-location","name":"{{ lead->name }}"}',
            'body_oportunidad' => '{"pipelineId":"original-pipeline","contactId":"{{contactId}}"}',
        ]);
    }

    public function test_loads_with_saved_token_and_selects_stages_without_another_request_or_saving(): void
    {
        $integration = $this->integration();
        $before = $integration->fresh()->getAttributes();
        Http::fake(['services.leadconnectorhq.com/*' => Http::response(['pipelines' => [
            ['id' => 'pipe-1', 'name' => 'Ventas', 'showInFunnel' => false, 'unexpected' => 'discard-me', 'stages' => [
                ['id' => 'stage-2', 'name' => 'Calificado', 'position' => 1],
                ['id' => 'stage-1', 'name' => 'Nuevo', 'position' => 0, 'stageWinProbability' => 0, 'showInFunnel' => true],
            ]],
            ['id' => 'pipe-2', 'name' => 'Sin etapas'],
        ]])]);

        $component = Livewire::test(GohighlevelPipelineCatalog::class, ['integrationId' => $integration->id]);
        Http::assertNothingSent();
        $component->set('locationId', ' loc-1 ')
            ->assertSet('locationId', 'loc-1')
            ->assertSet('loaded', true)
            ->assertSee('Ventas')
            ->set('pipelineId', 'pipe-1')
            ->assertSeeInOrder(['Nuevo', 'Calificado'])
            ->assertSee('stage-1')
            ->assertDontSee('discard-me')
            ->assertDontSee('private-test-token')
            ->set('pipelineId', 'pipe-2')
            ->assertSee('Este pipeline no tiene stages.');

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === 'https://services.leadconnectorhq.com/opportunities/pipelines?locationId=loc-1'
            && $request->hasHeader('Authorization', 'Bearer private-test-token')
            && $request->hasHeader('Version', 'v3')
            && $request->hasHeader('Accept', 'application/json'));
        $this->assertSame($before, $integration->fresh()->getAttributes());

        $component->set('locationId', '')
            ->assertSet('pipelines', [])
            ->assertSet('pipelineId', '')
            ->assertSet('loaded', false);
        Http::assertSentCount(1);
    }

    public function test_changing_location_removes_previous_selection_even_when_the_request_fails(): void
    {
        Http::fakeSequence()->push(['pipelines' => [['id' => 'old', 'name' => 'Anterior']]])->push([], 403);
        Livewire::test(GohighlevelPipelineCatalog::class, ['integrationId' => $this->integration()->id])
            ->set('locationId', 'loc-1')->set('pipelineId', 'old')
            ->set('locationId', 'loc-2')
            ->assertSet('pipelineId', '')->assertSet('pipelines', [])
            ->assertSee('Sin acceso.')->assertDontSee('Anterior');
    }

    public static function errorResponses(): array
    {
        return [
            [401, ['message' => 'private-test-token'], 'Token no válido.'],
            [403, [], 'Sin acceso.'],
            [422, [], 'Revisa el locationId.'],
            [429, [], 'límite de consultas'],
            [500, ['message' => 'private-test-token'], 'No fue posible consultar'],
            [200, ['invalid' => []], 'respuesta de pipelines no válida'],
        ];
    }

    #[DataProvider('errorResponses')]
    public function test_handles_errors_without_exposing_upstream_bodies(int $status, array $body, string $message): void
    {
        Http::fake(['*' => Http::response($body, $status)]);
        Livewire::test(GohighlevelPipelineCatalog::class, ['integrationId' => $this->integration()->id])
            ->set('locationId', 'loc-1')->assertSee($message)
            ->assertSet('loaded', false)->assertSet('pipelines', [])->assertDontSee('private-test-token');
    }

    public function test_handles_connection_failure_and_allows_retry(): void
    {
        $attempt = 0;
        Http::fake(function () use (&$attempt) {
            if ($attempt++ === 0) {
                throw new ConnectionException('private-test-token');
            }

            return Http::response(['pipelines' => []]);
        });
        $component = Livewire::test(GohighlevelPipelineCatalog::class, ['integrationId' => $this->integration()->id])
            ->set('locationId', 'loc-1')->assertSee('No se pudo conectar')->assertDontSee('private-test-token');
        $component->call('loadPipelines')->assertSet('catalogError', '')
            ->assertSee('No se encontraron pipelines');
    }

    public function test_rejects_invalid_location_without_an_http_request(): void
    {
        Livewire::test(GohighlevelPipelineCatalog::class, ['integrationId' => $this->integration()->id])
            ->set('locationId', 'https://example.com/')->assertHasErrors(['locationId'])
            ->set('locationId', '')->assertHasNoErrors(['locationId']);
        Http::assertNothingSent();
    }

    public function test_requires_a_saved_token_and_the_correct_integration_type(): void
    {
        Livewire::test(GohighlevelPipelineCatalog::class, ['integrationId' => $this->integration(token: null)->id])
            ->set('locationId', 'loc-1')->assertSee('No existe token');
        Livewire::test(GohighlevelPipelineCatalog::class, ['integrationId' => $this->integration('GoHighLevel')->id])
            ->set('locationId', 'loc-1')->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_requires_authentication(): void
    {
        auth()->logout();
        Livewire::test(GohighlevelPipelineCatalog::class, ['integrationId' => 1])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_integration_id_cannot_be_changed_from_the_browser(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(GohighlevelPipelineCatalog::class, ['integrationId' => $this->integration()->id])
            ->set('integrationId', 999);
    }

    public function test_parent_only_shows_catalog_for_saved_opportunity_token_and_preserves_json_fields(): void
    {
        foreach ([['GoHighLevel-Oportunidad', 'secret', true], ['GoHighLevel', 'secret', false], ['GoHighLevel-Oportunidad', null, false]] as [$type, $token, $visible]) {
            $integration = $this->integration($type, $token);
            $component = Volt::test('integrations.form', [
                'integration' => $integration, 'customerOptions' => collect(),
                'typeOptions' => Integrationtype::all(),
            ]);
            if ($visible) {
                $component->assertSee('Consulta de pipelines y stages')
                    ->assertSet('typeData.gohighlevel_oportunidad.body', $integration->body)
                    ->assertSet('typeData.gohighlevel_oportunidad.body_oportunidad', $integration->body_oportunidad)
                    ->assertSet('typeData.gohighlevel_oportunidad.tokent', '')
                    ->call('selectType', 999)->assertDontSee('Consulta de pipelines y stages');
            } else {
                $component->assertDontSee('Consulta de pipelines y stages');
            }
        }
        Http::assertNothingSent();
    }
}
