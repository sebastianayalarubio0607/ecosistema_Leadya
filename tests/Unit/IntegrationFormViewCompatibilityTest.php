<?php

namespace Tests\Unit;

use App\Models\Integration;
use Illuminate\Support\Facades\Blade;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IntegrationFormViewCompatibilityTest extends TestCase
{
    public static function formModes(): array
    {
        return [
            'current create' => [false, false],
            'current edit' => [false, true],
            'cached create' => [true, false],
            'cached edit' => [true, true],
        ];
    }

    #[DataProvider('formModes')]
    public function test_current_and_cached_pages_render_the_new_form(bool $legacy, bool $edit): void
    {
        $integration = new Integration(['name' => 'Prueba de formulario', 'status' => 1]);
        if ($edit) {
            $integration->id = 123;
            $integration->exists = true;
        }

        $template = $legacy ? <<<'BLADE'
            <form method="POST" action="{{ $integration->exists ? route('integrations.update', $integration->id) : route('integrations.store') }}">
                @csrf
                @if($integration->exists) @method('PUT') @endif
                @include('integrations._form')
            </form>
            BLADE : <<<'BLADE'
            <livewire:integrations.form :integration="$integration" :customer-options="$customers" :type-options="$types" />
            BLADE;

        $html = Blade::render($template, [
            'integration' => $integration,
            'customers' => collect(),
            'types' => collect(),
        ]);

        $this->assertStringContainsString('Datos generales', $html);
        $this->assertStringContainsString('data-integration-form-version="2026-09-11-livewire"', $html);
        $this->assertStringContainsString('name="description"', $html);
        $this->assertStringContainsString('wire:id=', $html);
        $this->assertSame(1, preg_match_all('/<form\b/i', $html));
        $this->assertSame(1, substr_count($html, '</form>'));
        $this->assertSame(1, substr_count($html, 'name="_token"'));
        $this->assertSame($edit ? 1 : 0, substr_count($html, 'name="_method"'));
        $this->assertStringContainsString('action="'.($edit
            ? route('integrations.update', 123)
            : route('integrations.store')).'"', $html);
        if ($edit) {
            $this->assertStringContainsString('value="PUT"', $html);
        }
    }

    public function test_legacy_mode_survives_a_livewire_update(): void
    {
        Volt::test('integrations.form', [
            'integration' => new Integration(),
            'customerOptions' => collect(),
            'typeOptions' => collect(),
            'embeddedInLegacyForm' => true,
        ])
            ->set('form.name', 'Nombre actualizado')
            ->assertSet('embeddedInLegacyForm', true)
            ->assertSee('Datos generales')
            ->assertDontSeeHtml('<form');
    }

    public function test_actual_create_and_edit_pages_use_the_current_component(): void
    {
        $this->withoutVite();
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());

        foreach (['create', 'edit'] as $page) {
            $integration = new Integration(['name' => 'Prueba de pagina']);
            if ($page === 'edit') {
                $integration->id = 123;
                $integration->exists = true;
            }
            $data = ['integration' => $integration, 'leadFields' => []];
            foreach (['customers', 'types', 'kommoPipelineConditions', 'atomWebhooks',
                'atomConditions', 'letyWebhooks', 'letyConditions', 'freshworksVariableMappings',
                'integrationVariableMappings', 'integrationVariables', 'integrationVariableConditions'] as $key) {
                $data[$key] = collect();
            }

            $html = view('integrations.'.$page, $data)->render();

            $this->assertStringContainsString('data-integration-form-version="2026-09-11-livewire"', $html);
            $this->assertStringContainsString('Datos generales', $html);
            $this->assertStringContainsString('name="description"', $html);
            $this->assertStringNotContainsString('Guarda aqui el enlace directo', $html);
        }
    }
}
