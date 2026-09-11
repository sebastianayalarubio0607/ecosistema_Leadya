<?php

use App\Models\Integration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component {
    public ?int $integrationId = null;
    public bool $isEdit = false;
    public ?int $originalTypeId = null;
    public bool $confirmTypeChange = false;
    public string $typeKey = '';
    public string $publicKey = '';
    public array $customers = [];
    public array $types = [];
    public array $leadFields = [];
    public array $form = [];
    public array $typeData = [];
    public array $storedSecrets = [];
    public array $kommoConditions = [];
    public array $atomWebhooks = [];
    public array $atomConditions = [];
    public string $atomDefaultKey = '';
    public array $letyWebhooks = [];
    public array $letyConditions = [];
    public array $freshworksMappings = [];
    public array $integrationMappings = [];
    public array $variables = [];
    public array $variableConditions = [];
    public array $kommoPipelines = [];
    public array $defaultKommoStatuses = [];
    public array $conditionKommoStatuses = [];
    public string $kommoCatalogError = '';

    private const CUSTOM_VARIABLE_TYPES = [
        'text' => 'Texto', 'number' => 'Numero', 'boolean' => 'Booleano',
        'binary' => 'Binario', 'url' => 'URL', 'json' => 'JSON', 'any' => 'Cualquier',
    ];

    private const CONDITION_OPERATORS = [
        'equals' => 'Igual a', 'not_equals' => 'Distinto de', 'greater_than' => 'Mayor que',
        'less_than' => 'Menor que', 'greater_or_equal' => 'Mayor o igual',
        'less_or_equal' => 'Menor o igual', 'and_logic' => 'Y logico', 'or_logic' => 'O logico',
        'negation' => 'Negacion', 'exists' => 'Existe', 'not_exists' => 'No existe',
        'empty' => 'Esta vacio', 'not_empty' => 'No esta vacio', 'is' => 'Es',
        'not_is' => 'No es', 'contains' => 'Contiene', 'not_contains' => 'No contiene',
        'starts_with' => 'Empieza con', 'not_starts_with' => 'No empieza con',
        'ends_with' => 'Termina con', 'not_ends_with' => 'No termina con',
        'in_list' => 'Esta dentro de una lista', 'not_in_list' => 'No esta dentro de una lista',
        'key_exists' => 'Existe una llave', 'key_not_exists' => 'No existe una llave',
        'record_exists' => 'Existe un registro', 'record_not_exists' => 'No existe un registro',
        'has_elements' => 'Hay elementos', 'no_elements' => 'No hay elementos',
        'matches_pattern' => 'Coincide con un patron', 'not_matches_pattern' => 'No coincide con un patron',
        'all_conditions' => 'Todas las condiciones se cumplen',
        'any_condition' => 'Alguna condicion se cumple', 'no_conditions' => 'Ninguna condicion se cumple',
    ];

    public function mount(
        $integration,
        $customerOptions,
        $typeOptions,
        array $leadFieldOptions = [],
        $initialKommoConditions = [],
        $initialAtomWebhooks = [],
        $initialAtomConditions = [],
        $initialLetyWebhooks = [],
        $initialLetyConditions = [],
        $initialFreshworksMappings = [],
        $initialIntegrationMappings = [],
        $initialVariables = [],
        $initialVariableConditions = []
    ): void {
        $this->integrationId = $integration->exists ? (int) $integration->id : null;
        $this->isEdit = (bool) $integration->exists;
        $this->originalTypeId = $integration->integrationtype_id ? (int) $integration->integrationtype_id : null;
        $this->publicKey = (string) ($integration->public_key ?? '');
        $this->customers = collect($customerOptions)->map(fn ($item) => ['id' => (int) $item->id, 'name' => (string) $item->name])->values()->all();
        $this->types = collect($typeOptions)->map(fn ($item) => [
            'id' => (int) $item->id,
            'name' => (string) $item->name,
            'key' => $this->normalizeType((string) $item->name),
        ])->values()->all();
        $this->leadFields = array_values(array_filter($leadFieldOptions));

        $selectedTypeId = old('integrationtype_id', $integration->integrationtype_id);
        $this->form = [
            'name' => (string) old('name', $integration->name ?? ''),
            'description' => (string) old('description', $integration->description ?? ''),
            'customer_id' => (string) old('customer_id', $integration->customer_id ?? ''),
            'integrationtype_id' => (string) ($selectedTypeId ?? ''),
            'urldestino' => (string) old('urldestino', $integration->urldestino ?? ''),
            'status' => (string) old('status', isset($integration->status) ? (int) $integration->status : 1),
            'priority' => (string) old('priority', $integration->priority ?? 100),
            'disable_integration_id_crm_prefix' => (bool) old('disable_integration_id_crm_prefix', $integration->disable_integration_id_crm_prefix ?? false),
            'crm_id_prefix' => (string) old('crm_id_prefix', $integration->crm_id_prefix ?? ''),
        ];
        $this->typeKey = $this->typeKeyForId($selectedTypeId);
        $this->initializeTypeData($integration);
        $this->initializeCollections(
            $initialKommoConditions, $initialAtomWebhooks, $initialAtomConditions, $initialLetyWebhooks,
            $initialLetyConditions, $initialFreshworksMappings, $initialIntegrationMappings,
            $initialVariables, $initialVariableConditions
        );
    }

    private function initializeTypeData($integration): void
    {
        foreach (collect($this->types)->pluck('key')->filter()->unique() as $key) {
            $this->typeData[$key] = [];
        }

        if ($this->typeKey === '') {
            return;
        }

        $baseUrl = (string) ($integration->url ?? '');
        if (in_array($this->typeKey, ['hubspot', 'zapnito_invitacion'], true) && $baseUrl !== '') {
            $parts = parse_url($baseUrl);
            if (isset($parts['scheme'], $parts['host'])) {
                $baseUrl = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
            }
        }

        $fields = [
            'url', 'tokent', 'body', 'body_oportunidad', 'crm_Id_phone', 'crm_Id_email',
            'crm_Id_service', 'crm_Id_fuente', 'client_id', 'client_secret', 'code',
            'access_token', 'refresh_token', 'territory_id', 'owner_id', 'city',
            'lead_source_id', 'custom_field', 'url_credenciales', 'username', 'password',
            'kommo_pipeline_default_pipeline_id', 'kommo_pipeline_default_pipeline_name',
            'kommo_pipeline_default_status_id', 'kommo_pipeline_default_status_name',
        ];

        foreach ($fields as $field) {
            $storedField = $field === 'access_token' ? 'tokent' : $field;
            $isSecret = in_array($field, ['tokent', 'access_token', 'client_secret', 'refresh_token', 'password'], true);
            $value = $isSecret ? '' : ($field === 'url' ? $baseUrl : ($integration->{$storedField} ?? ''));
            $this->typeData[$this->typeKey][$field] = (string) old($field, $value);
        }

        $this->storedSecrets = [
            'tokent' => $this->isEdit && filled($integration->tokent ?? null),
            'access_token' => $this->isEdit && filled($integration->tokent ?? null),
            'client_secret' => $this->isEdit && filled($integration->client_secret ?? null),
            'refresh_token' => $this->isEdit && filled($integration->refresh_token ?? null),
            'password' => $this->isEdit && filled($integration->password ?? null),
        ];
    }

    private function initializeCollections(...$collections): void
    {
        [$kommo, $atomHooks, $atomRules, $letyHooks, $letyRules, $freshworks, $mappings, $variables, $conditions] = $collections;
        $this->kommoConditions = $this->rows(old('kommo_pipeline_conditions', collect($kommo)->map(fn ($row) => [
            'lead_field' => $row->lead_field, 'expected_value' => $row->expected_value,
            'pipeline_id' => $row->pipeline_id, 'pipeline_name' => $row->pipeline_name,
            'status_id' => $row->status_id, 'status_name' => $row->status_name,
            'active' => (bool) $row->active,
        ])->all()), 'kommo');

        $this->atomWebhooks = $this->rows(old('atom_webhooks', collect($atomHooks)->map(fn ($row) => [
            'key' => (string) $row->id, 'name' => $row->name, 'url' => $row->url,
            'active' => (bool) $row->active, 'is_default' => (bool) $row->is_default,
        ])->all()), 'atom_webhook');
        if ($this->atomWebhooks === []) {
            $this->addAtomWebhook();
        }
        $this->atomDefaultKey = (string) (collect($this->atomWebhooks)->firstWhere('is_default', true)['_key'] ?? $this->atomWebhooks[0]['_key']);
        $this->atomConditions = $this->rows(old('atom_conditions', collect($atomRules)->map(fn ($row) => [
            'lead_field' => $row->lead_field, 'expected_value' => $row->expected_value,
            'webhook_key' => (string) $row->atom_webhook_id, 'active' => (bool) $row->active,
        ])->all()), 'atom_condition');
        if ($this->atomConditions === []) {
            $this->addAtomCondition();
        }

        $defaultLetyBody = "name={{\$lead->name}}\nemail={{\$lead.email}}\nphone={{\$lead->phone}}";
        $this->letyWebhooks = $this->rows(old('lety_webhooks', collect($letyHooks)->map(fn ($row) => [
            'key' => (string) $row->id, 'name' => $row->name, 'url' => $row->url,
            'body' => $row->body, 'active' => (bool) $row->active,
        ])->all()), 'lety_webhook');
        if ($this->letyWebhooks === []) {
            $this->addLetyWebhook($defaultLetyBody);
        }
        $this->letyConditions = $this->rows(old('lety_conditions', collect($letyRules)->map(fn ($row) => [
            'lead_field' => $row->lead_field, 'expected_value' => $row->expected_value,
            'webhook_key' => (string) $row->lety_webhook_id, 'active' => (bool) $row->active,
        ])->all()), 'lety_condition');
        if ($this->letyConditions === []) {
            $this->addLetyCondition();
        }

        $map = fn ($row) => [
            'target_variable' => $row->target_variable, 'lead_field' => $row->lead_field,
            'expected_value' => $row->expected_value, 'mapped_value' => $row->mapped_value,
            'active' => (bool) $row->active,
        ];
        $this->freshworksMappings = $this->rows(old('freshworks_variable_mappings', collect($freshworks)->map($map)->all()), 'freshworks');
        $this->integrationMappings = $this->rows(old('integration_variable_mappings', collect($mappings)->map($map)->all()), 'mapping');
        $this->variables = $this->rows(old('integration_variables', collect($variables)->map(fn ($row) => [
            'name' => $row->name, 'value' => $row->value, 'type' => $row->type, 'active' => (bool) $row->active,
        ])->all()), 'variable');
        $this->variableConditions = $this->rows(old('integration_variable_conditions', collect($conditions)->map(fn ($row) => [
            'target_variable' => $row->target_variable, 'source_type' => $row->source_type,
            'source_key' => $row->source_key, 'operator' => $row->operator,
            'comparison_value' => $row->comparison_value, 'result_value' => $row->result_value,
            'result_type' => $row->result_type, 'active' => (bool) $row->active,
        ])->all()), 'variable_condition');
    }

    private function rows($rows, string $prefix): array
    {
        return collect($rows ?? [])->map(function ($row) use ($prefix) {
            $row = (array) $row;
            $row['_key'] = (string) ($row['_key'] ?? $row['key'] ?? $this->newKey($prefix));
            $row['active'] = array_key_exists('active', $row) ? (bool) $row['active'] : true;
            return $row;
        })->values()->all();
    }

    private function newKey(string $prefix): string
    {
        return $prefix.'_'.Str::lower(Str::random(12));
    }

    public function selectType($id): void
    {
        $this->form['integrationtype_id'] = (string) $id;
        $this->typeKey = $this->typeKeyForId($id);
        $this->confirmTypeChange = false;
        $this->typeData[$this->typeKey] ??= [];
    }

    private function typeKeyForId($id): string
    {
        return (string) (collect($this->types)->firstWhere('id', (int) $id)['key'] ?? '');
    }

    private function normalizeType(string $name): string
    {
        $key = Str::of($name)->ascii()->lower()->replace([' ', '-'], '_')->replaceMatches('/_+/', '_')->trim('_')->toString();
        return match ($key) {
            'go_high_level', 'leadconnector', 'lead_connector' => 'gohighlevel',
            'gohighleve_oportunidad', 'gohighlevel_opportunity' => 'gohighlevel_oportunidad',
            'kommo_pipeline' => 'kommopipeline', 'atom_webhook', 'atom_webhooks' => 'atom',
            'lety_webhook', 'lety_webhooks' => 'lety',
            'zapnito', 'zapnito_invitation', 'zapnito_invitations' => 'zapnito_invitacion',
            default => $key,
        };
    }

    public function addKommoCondition(): void { $this->kommoConditions[] = ['_key' => $this->newKey('kommo'), 'lead_field' => '', 'expected_value' => '', 'pipeline_id' => '', 'pipeline_name' => '', 'status_id' => '', 'status_name' => '', 'active' => true]; }
    public function removeKommoCondition(int $index): void { unset($this->kommoConditions[$index]); $this->kommoConditions = array_values($this->kommoConditions); }
    public function addAtomWebhook(): void { $this->atomWebhooks[] = ['_key' => $this->newKey('atom_hook'), 'key' => $this->newKey('atom'), 'name' => '', 'url' => '', 'active' => true, 'is_default' => $this->atomWebhooks === []]; }
    public function removeAtomWebhook(int $index): void { $key = $this->atomWebhooks[$index]['key'] ?? ''; unset($this->atomWebhooks[$index]); $this->atomWebhooks = array_values($this->atomWebhooks); $this->atomConditions = array_values(array_filter($this->atomConditions, fn ($row) => ($row['webhook_key'] ?? '') !== $key)); $this->atomDefaultKey = (string) ($this->atomWebhooks[0]['_key'] ?? ''); }
    public function addAtomCondition(): void { $this->atomConditions[] = ['_key' => $this->newKey('atom_rule'), 'lead_field' => '', 'expected_value' => '', 'webhook_key' => '', 'active' => true]; }
    public function removeAtomCondition(int $index): void { unset($this->atomConditions[$index]); $this->atomConditions = array_values($this->atomConditions); }
    public function addLetyWebhook(string $body = ''): void { $this->letyWebhooks[] = ['_key' => $this->newKey('lety_hook'), 'key' => $this->newKey('lety'), 'name' => '', 'url' => '', 'body' => $body, 'active' => true]; }
    public function removeLetyWebhook(int $index): void { $key = $this->letyWebhooks[$index]['key'] ?? ''; unset($this->letyWebhooks[$index]); $this->letyWebhooks = array_values($this->letyWebhooks); $this->letyConditions = array_values(array_filter($this->letyConditions, fn ($row) => ($row['webhook_key'] ?? '') !== $key)); }
    public function addLetyCondition(): void { $this->letyConditions[] = ['_key' => $this->newKey('lety_rule'), 'lead_field' => '', 'expected_value' => '', 'webhook_key' => '', 'active' => true]; }
    public function removeLetyCondition(int $index): void { unset($this->letyConditions[$index]); $this->letyConditions = array_values($this->letyConditions); }
    public function addFreshworksMapping(): void { $this->freshworksMappings[] = $this->newMapping('freshworks'); }
    public function removeFreshworksMapping(int $index): void { unset($this->freshworksMappings[$index]); $this->freshworksMappings = array_values($this->freshworksMappings); }
    public function addIntegrationMapping(): void { $this->integrationMappings[] = $this->newMapping('mapping'); }
    public function removeIntegrationMapping(int $index): void { unset($this->integrationMappings[$index]); $this->integrationMappings = array_values($this->integrationMappings); }
    private function newMapping(string $prefix): array { return ['_key' => $this->newKey($prefix), 'target_variable' => '', 'lead_field' => '', 'expected_value' => '', 'mapped_value' => '', 'active' => true]; }
    public function addVariable(): void { $this->variables[] = ['_key' => $this->newKey('variable'), 'name' => '', 'value' => '', 'type' => 'text', 'active' => true]; }
    public function removeVariable(int $index): void { unset($this->variables[$index]); $this->variables = array_values($this->variables); }
    public function addVariableCondition(): void { $this->variableConditions[] = ['_key' => $this->newKey('variable_rule'), 'target_variable' => '', 'source_type' => 'lead', 'source_key' => '', 'operator' => 'equals', 'comparison_value' => '', 'result_value' => '', 'result_type' => 'text', 'active' => true]; }
    public function removeVariableCondition(int $index): void { unset($this->variableConditions[$index]); $this->variableConditions = array_values($this->variableConditions); }

    public function loadKommoPipelines(): void
    {
        if (! $this->integrationId || $this->typeKey !== 'kommopipeline') return;
        try {
            $integration = Integration::query()->findOrFail($this->integrationId);
            $this->kommoPipelines = $this->kommoRequest($integration, '/api/v4/leads/pipelines', '_embedded.pipelines');
            $this->loadDefaultKommoStatuses();
            foreach (array_keys($this->kommoConditions) as $index) $this->loadConditionKommoStatuses($index);
        } catch (\Throwable $exception) {
            $this->kommoCatalogError = 'No fue posible consultar pipelines. Se conservaran los valores guardados.';
        }
    }

    public function loadDefaultKommoStatuses(): void
    {
        $pipelineId = $this->typeData['kommopipeline']['kommo_pipeline_default_pipeline_id'] ?? '';
        $this->typeData['kommopipeline']['kommo_pipeline_default_pipeline_name'] = $this->labelFor($this->kommoPipelines, $pipelineId);
        $this->defaultKommoStatuses = $this->loadKommoStatuses($pipelineId);
    }

    public function loadConditionKommoStatuses(int $index): void
    {
        $pipelineId = $this->kommoConditions[$index]['pipeline_id'] ?? '';
        $this->kommoConditions[$index]['pipeline_name'] = $this->labelFor($this->kommoPipelines, $pipelineId);
        $this->conditionKommoStatuses[$index] = $this->loadKommoStatuses($pipelineId);
    }

    public function setDefaultKommoStatusName(): void
    {
        $id = $this->typeData['kommopipeline']['kommo_pipeline_default_status_id'] ?? '';
        $this->typeData['kommopipeline']['kommo_pipeline_default_status_name'] = $this->labelFor($this->defaultKommoStatuses, $id);
    }

    public function setConditionKommoStatusName(int $index): void
    {
        $id = $this->kommoConditions[$index]['status_id'] ?? '';
        $this->kommoConditions[$index]['status_name'] = $this->labelFor($this->conditionKommoStatuses[$index] ?? [], $id);
    }

    private function loadKommoStatuses(string $pipelineId): array
    {
        if (! $this->integrationId || $pipelineId === '') return [];
        try {
            $integration = Integration::query()->findOrFail($this->integrationId);
            return $this->kommoRequest($integration, "/api/v4/leads/pipelines/{$pipelineId}/statuses", '_embedded.statuses');
        } catch (\Throwable $exception) {
            $this->kommoCatalogError = 'No fue posible consultar algunos estados de Kommo.';
            return [];
        }
    }

    private function kommoRequest(Integration $integration, string $path, string $dataPath): array
    {
        $token = preg_replace('/^bearer\s+/i', '', trim((string) $integration->tokent));
        if (blank($integration->url) || blank($token)) return [];
        $response = Http::acceptJson()->withToken($token)->get(rtrim((string) $integration->url, '/').$path);
        if (! $response->successful()) return [];
        return collect(data_get($response->json(), $dataPath, []))->map(fn ($item) => [
            'id' => (string) data_get($item, 'id'), 'name' => (string) data_get($item, 'name'),
        ])->filter(fn ($item) => $item['id'] !== '')->values()->all();
    }

    private function labelFor(array $items, string $id): string
    {
        return (string) (collect($items)->firstWhere('id', (string) $id)['name'] ?? $id);
    }

    public function variableTypes(): array { return self::CUSTOM_VARIABLE_TYPES; }
    public function conditionOperators(): array { return self::CONDITION_OPERATORS; }
};
?>

@php
    $inputClass = 'w-full rounded-lg border border-white/10 bg-slate-900/60 p-2 text-white placeholder-white/40 focus:border-indigo-400 focus:ring-indigo-400';
    $labelClass = 'mb-1 block text-sm font-medium text-white/70';
    $typeChanged = $isEdit && (int) $form['integrationtype_id'] !== (int) $originalTypeId;
    $supportsPrefix = in_array($typeKey, ['kommo', 'kommopipeline', 'freshworks', 'hubspot', 'gohighlevel', 'gohighlevel_oportunidad'], true);
    $supportsVariables = in_array($typeKey, ['kommopipeline', 'atom', 'zoho', 'freshworks', 'salesforce', 'monday', 'lety', 'hubspot', 'gohighlevel', 'gohighlevel_oportunidad', 'zapnito_invitacion'], true);
    $supportsMappings = in_array($typeKey, ['atom', 'zoho', 'salesforce', 'monday', 'lety', 'hubspot', 'gohighlevel', 'gohighlevel_oportunidad', 'zapnito_invitacion'], true);
@endphp

<div class="border border-white/10 bg-zinc-950/25 p-4 backdrop-blur sm:p-6 rounded-xl">
    <form method="POST" action="{{ $isEdit ? route('integrations.update', $integrationId) : route('integrations.store') }}" class="space-y-8">
        @csrf
        @if($isEdit) @method('PUT') @endif

        @if($errors->any())
            <section class="border border-rose-300/20 bg-rose-500/10 p-4 text-sm text-rose-100" role="alert">
                <h3 class="font-semibold">Revisa los datos del formulario</h3>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @include('livewire.integrations.partials.general')
        @include('livewire.integrations.partials.type-fields')

        @if($supportsVariables)
            @include('livewire.integrations.partials.variables')
        @endif

        @if($supportsMappings)
            @include('livewire.integrations.partials.mappings', ['items' => $integrationMappings, 'scope' => 'integration'])
        @endif

        @if($supportsVariables)
            @include('livewire.integrations.partials.variable-conditions')
        @endif

        @if($isEdit)
            <section class="border-t border-white/10 pt-6 ">
                <h3 class="text-sm font-semibold text-white">Public key</h3>
                <div class="mt-2 break-all border border-white/10 bg-slate-900/60 p-3 font-mono text-xs text-white/70 rounded-xl">{{ $publicKey }}</div>
                <label class="mt-3 inline-flex items-center gap-2 text-sm text-white/80">
                    <input type="hidden" name="regenerate_public_key" value="0">
                    <input type="checkbox" name="regenerate_public_key" value="1" class="rounded border-white/20 bg-slate-900 text-indigo-500">
                    Regenerar public_key al guardar
                </label>
            </section>
        @endif

        <div class="sticky bottom-0 z-10 flex flex-wrap items-center justify-between gap-3 border-t border-white/10 bg-zinc-950/25 p-4 backdrop-blur rounded-xl">
            <div class="text-sm text-white/50" wire:dirty>Hay cambios pendientes.</div>
            <div class="flex gap-2">
                <a href="{{ route('integrations.index') }}" class="rounded-lg border border-white/10 bg-white/10 px-4 py-2 text-white hover:bg-white/15">Cancelar</a>
                <button type="submit"
                        @disabled($typeChanged && ! $confirmTypeChange)
                        wire:loading.attr="disabled"
                        class="rounded-lg border border-indigo-300/20 bg-indigo-500/30 px-4 py-2 text-white hover:bg-indigo-500/40 disabled:cursor-not-allowed disabled:opacity-50">
                    <span wire:loading.remove>Guardar</span>
                    <span wire:loading>Actualizando...</span>
                </button>
            </div>
        </div>
    </form>
</div>
