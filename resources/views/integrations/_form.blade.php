<div class="grid gap-4">
    <div>
        <label class="block mb-1 text-white/70">Nombre *</label>
        <input name="name" value="{{ old('name', $integration->name ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
               required>
        @error('name') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
    </div>

    <div>
        <label class="block mb-1 text-white/70">Cliente *</label>
        <select name="customer_id" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" required>
            <option value="">Seleccione...</option>
            @foreach($customers as $c)
                <option value="{{ $c->id }}" {{ (int) old('customer_id', $integration->customer_id ?? 0) === (int) $c->id ? 'selected' : '' }}>
                    {{ $c->name }}
                </option>
            @endforeach
        </select>
        @error('customer_id') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
    </div>

    <div>
        <label class="block mb-1 text-white/70">Tipo de Integracion *</label>
        <select id="integrationtype_id" name="integrationtype_id" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" required>
            <option value="">Seleccione...</option>
            @foreach($types as $t)
                <option value="{{ $t->id }}" data-key="{{ $t->key ?? \Illuminate\Support\Str::slug($t->name, '_') }}" {{ (int) old('integrationtype_id', $integration->integrationtype_id ?? 0) === (int) $t->id ? 'selected' : '' }}>
                    {{ $t->name }}
                </option>
            @endforeach
        </select>
        @error('integrationtype_id') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
    </div>

    <div data-base-url-block>
        <label class="block mb-1 text-white/70">URL *</label>
        <input name="url" value="{{ old('url', $integration->url ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40" placeholder="https://..." data-base-url-input>
        @error('url') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
    </div>

    <div>
        <label class="block mb-1 text-white/70">Status *</label>
        <select name="status" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" required>
            <option value="1" @selected((string) old('status', (int) ($integration->status ?? 1)) === '1')>Activo</option>
            <option value="0" @selected((string) old('status', (int) ($integration->status ?? 1)) === '0')>Inactivo</option>
        </select>
        @error('status') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
    </div>

    <div>
        <label class="block mb-1 text-white/70">Prioridad *</label>
        <input name="priority"
               type="number"
               min="0"
               step="1"
               value="{{ old('priority', $integration->priority ?? 100) }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
               required>
        @error('priority') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
        <p class="mt-1 text-xs text-white/50">Las integraciones con mayor número se procesan primero. Si empatan, se ordenan por ID.</p>
    </div>

    <div class="grid grid-cols-1 gap-4 hidden" data-show-for="kommo kommopipeline freshworks hubspot gohighlevel">
        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
            <p class="text-sm text-white/70">
                Si está desactivado, se usa el ID de integración como prefijo del <span class="font-mono">crm_id</span>.
                Si está activado, se usa el prefijo manual configurado abajo.
            </p>

<div class="mt-3">
    <input type="hidden" name="disable_integration_id_crm_prefix" value="0">

    <x-toggle-switch
        name="disable_integration_id_crm_prefix"
        value="1"
        label="Desactivar id_crm con ID de integración"
        data-crm-prefix-toggle
        :checked="(int) old('disable_integration_id_crm_prefix', $integration->disable_integration_id_crm_prefix ?? 0) === 1"
    />
</div>

            <div class="mt-4">
                <label class="block mb-1 text-white/70">Prefijo manual para crm_id</label>
                <input name="crm_id_prefix" value="{{ old('crm_id_prefix', $integration->crm_id_prefix ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40 disabled:cursor-not-allowed disabled:opacity-50" placeholder="Ej: fw-cliente-a" data-crm-prefix-input>
                @error('crm_id_prefix') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
                <p class="mt-1 text-xs text-white/50">Este prefijo solo se usa cuando activas la opción anterior y se guardará como <span class="font-mono">prefijo-manual-idExterno</span>.</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 hidden" data-show-for="kommo">
        <div><label class="block mb-1 text-white/70">token</label><input name="tokent" value="{{ old('tokent', $integration->tokent ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">@error('tokent') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
        <div><label class="block mb-1 text-white/70">crm_Id_phone</label><input name="crm_Id_phone" value="{{ old('crm_Id_phone', $integration->crm_Id_phone ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">@error('crm_Id_phone') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
        <div><label class="block mb-1 text-white/70">crm_Id_email</label><input name="crm_Id_email" value="{{ old('crm_Id_email', $integration->crm_Id_email ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">@error('crm_Id_email') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
        <div><label class="block mb-1 text-white/70">crm_Id_service</label><input name="crm_Id_service" value="{{ old('crm_Id_service', $integration->crm_Id_service ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">@error('crm_Id_service') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
        <div><label class="block mb-1 text-white/70">crm_Id_fuente</label><input name="crm_Id_fuente" value="{{ old('crm_Id_fuente', $integration->crm_Id_fuente ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">@error('crm_Id_fuente') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
    </div>

    @php
        $kommoPipelineBodyPlaceholder = <<<'JSON'
[
  {
    "name": "{{$lead->name}} {{$lead->last_name}}",
    "pipeline_id": "{{pipeline_id}}",
    "status_id": "{{status_id}}",
    "_embedded": {
      "contacts": [
        {
          "name": "{{$lead->name}} {{$lead->last_name}}",
          "custom_fields_values": [
            {
              "field_id": 123456,
              "values": [
                {
                  "value": "{{$lead->phone}}"
                }
              ]
            },
            {
              "field_id": 789101,
              "values": [
                {
                  "value": "{{$lead->email}}",
                  "enum_code": "WORK"
                }
              ]
            }
          ]
        }
      ]
    }
  }
]
JSON;

        $kommoPipelineStoredConditions = isset($kommoPipelineConditions)
            ? $kommoPipelineConditions->map(fn ($condition) => [
                'lead_field' => $condition->lead_field,
                'expected_value' => $condition->expected_value,
                'pipeline_id' => $condition->pipeline_id,
                'pipeline_name' => $condition->pipeline_name,
                'status_id' => $condition->status_id,
                'status_name' => $condition->status_name,
                'order' => $condition->order,
                'active' => $condition->active ? 1 : 0,
            ])->values()->all()
            : [];

        $kommoPipelineInitialConditions = collect(old('kommo_pipeline_conditions', $kommoPipelineStoredConditions))->values();
        $kommoPipelineTokenMask = \App\Support\SensitiveValue::mask($integration->tokent ?? null);
        $kommoPipelineCanFetch = $integration->exists && filled($integration->url ?? null) && filled($integration->tokent ?? null);
    @endphp

    <div class="grid grid-cols-1 gap-4 hidden" data-show-for="kommopipeline" data-kommo-pipeline-block>
        <div>
            <label class="block mb-1 text-white/70">Token de acceso *</label>
            <input name="tokent"
                   type="password"
                   value="{{ old('tokent') }}"
                   class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white"
                   placeholder="{{ ($integration->exists && filled($integration->tokent ?? null)) ? $kommoPipelineTokenMask : 'Bearer token de Kommo' }}"
                   data-required-for="kommopipeline">
            @error('tokent') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
            @if($integration->exists && filled($integration->tokent ?? null))
                <p class="mt-1 text-xs text-white/50">Deja este campo vacio para conservar el token guardado.</p>
            @endif
        </div>

        <div>
            <label class="block mb-1 text-white/70">Payload JSON configurable *</label>
            <textarea name="body"
                      rows="16"
                      class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 font-mono text-sm text-white"
                      placeholder="{{ $kommoPipelineBodyPlaceholder }}"
                      data-required-for="kommopipeline">{{ old('body', $integration->body ?? '') }}</textarea>
            @error('body') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
        </div>

        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-white">Pipeline por defecto</h3>
                    <p class="text-sm text-white/50">Se usa cuando ninguna condicionalidad coincide.</p>
                </div>
                @unless($kommoPipelineCanFetch)
                    <span class="rounded-lg border border-amber-300/20 bg-amber-500/10 px-2 py-1 text-xs text-amber-100">Guarda URL y token para consultar Kommo</span>
                @endunless
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="block mb-1 text-white/70">Pipeline de Kommo</label>
                    <input type="hidden" name="kommo_pipeline_default_pipeline_name" value="{{ old('kommo_pipeline_default_pipeline_name', $integration->kommo_pipeline_default_pipeline_name ?? '') }}" data-kommo-default-pipeline-name>
                    <select name="kommo_pipeline_default_pipeline_id"
                            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white"
                            data-kommo-default-pipeline
                            data-selected-id="{{ old('kommo_pipeline_default_pipeline_id', $integration->kommo_pipeline_default_pipeline_id ?? '') }}"
                            data-selected-name="{{ old('kommo_pipeline_default_pipeline_name', $integration->kommo_pipeline_default_pipeline_name ?? '') }}">
                        <option value="">Seleccione...</option>
                    </select>
                    @error('kommo_pipeline_default_pipeline_id') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label class="block mb-1 text-white/70">Status / columna de Kommo</label>
                    <input type="hidden" name="kommo_pipeline_default_status_name" value="{{ old('kommo_pipeline_default_status_name', $integration->kommo_pipeline_default_status_name ?? '') }}" data-kommo-default-status-name>
                    <select name="kommo_pipeline_default_status_id"
                            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white"
                            data-kommo-default-status
                            data-selected-id="{{ old('kommo_pipeline_default_status_id', $integration->kommo_pipeline_default_status_id ?? '') }}"
                            data-selected-name="{{ old('kommo_pipeline_default_status_name', $integration->kommo_pipeline_default_status_name ?? '') }}">
                        <option value="">Seleccione un pipeline...</option>
                    </select>
                    @error('kommo_pipeline_default_status_id') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-white">Condicionalidades</h3>
                    <p class="text-sm text-white/50">Evalua reglas en orden y usa el primer pipeline/status que coincida.</p>
                </div>
                <button type="button"
                        class="px-3 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10"
                        data-kommo-add-condition>
                    Agregar condicion
                </button>
            </div>

            @error('kommo_pipeline_conditions') <div class="mb-2 text-sm text-rose-300">{{ $message }}</div> @enderror

            <div class="overflow-x-auto rounded-xl border border-white/10">
                <table class="min-w-full text-sm">
                    <thead class="bg-white/5 text-white/70">
                        <tr>
                            <th class="text-left px-3 py-2">Campo Lead</th>
                            <th class="text-left px-3 py-2">Valor esperado</th>
                            <th class="text-left px-3 py-2">Pipeline</th>
                            <th class="text-left px-3 py-2">Status</th>
                            <th class="text-left px-3 py-2">Activa</th>
                            <th class="text-left px-3 py-2 w-24">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10 text-white/80" data-kommo-conditions-body>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @php
        $atomBodyPlaceholder = <<<'JSON'
{
  "name": "{{$lead->name}}",
  "email": "{{$lead.email}}",
  "phone": "{{$lead->phone}}"
}
JSON;

        $atomStoredWebhooks = isset($atomWebhooks)
            ? $atomWebhooks->map(fn ($webhook) => [
                'key' => (string) $webhook->id,
                'name' => $webhook->name,
                'url' => $webhook->url,
                'order' => $webhook->order,
                'active' => $webhook->active ? 1 : 0,
                'is_default' => $webhook->is_default ? 1 : 0,
            ])->values()->all()
            : [];

        $atomStoredConditions = isset($atomConditions)
            ? $atomConditions->map(fn ($condition) => [
                'lead_field' => $condition->lead_field,
                'expected_value' => $condition->expected_value,
                'webhook_key' => (string) $condition->atom_webhook_id,
                'order' => $condition->order,
                'active' => $condition->active ? 1 : 0,
            ])->values()->all()
            : [];

        $atomInitialWebhooks = collect(old('atom_webhooks', $atomStoredWebhooks))->values();
        $atomInitialConditions = collect(old('atom_conditions', $atomStoredConditions))->values();
        $atomTokenMask = \App\Support\SensitiveValue::mask($integration->tokent ?? null);
    @endphp

    <div class="grid grid-cols-1 gap-4 hidden" data-show-for="atom" data-atom-block>
        <div>
            <label class="block mb-1 text-white/70">Token de autenticacion *</label>
            <input name="tokent"
                   type="password"
                   value="{{ old('tokent') }}"
                   class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white"
                   placeholder="{{ ($integration->exists && filled($integration->tokent ?? null)) ? $atomTokenMask : 'Bearer token' }}"
                   data-required-for="atom">
            @error('tokent') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
            @if($integration->exists && filled($integration->tokent ?? null))
                <p class="mt-1 text-xs text-white/50">Deja este campo vacio para conservar el token guardado.</p>
            @endif
        </div>

        <div>
            <label class="block mb-1 text-white/70">Body JSON *</label>
            <textarea name="body"
                      rows="12"
                      class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 font-mono text-sm text-white"
                      placeholder="{{ $atomBodyPlaceholder }}"
                      data-required-for="atom">{{ old('body', $integration->body ?? '') }}</textarea>
            @error('body') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
        </div>

        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-white">Webhooks Atom</h3>
                    <p class="text-sm text-white/50">Configura los endpoints y el JSON que se enviara a cada uno.</p>
                </div>
                <button type="button"
                        class="px-3 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10"
                        data-atom-add-webhook>
                    Agregar webhook
                </button>
            </div>

            @error('atom_webhooks') <div class="mb-2 text-sm text-rose-300">{{ $message }}</div> @enderror

            <div class="overflow-x-auto rounded-xl border border-white/10">
                <table class="min-w-full text-sm">
                    <thead class="bg-white/5 text-white/70">
                        <tr>
                            <th class="text-left px-3 py-2">Nombre</th>
                            <th class="text-left px-3 py-2">URL</th>
                            <th class="text-left px-3 py-2">Por defecto</th>
                            <th class="text-left px-3 py-2">Activo</th>
                            <th class="text-left px-3 py-2 w-24">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10 text-white/80" data-atom-webhooks-body>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-white">Condiciones Atom</h3>
                    <p class="text-sm text-white/50">Si varias condiciones coinciden, se envian todos los webhooks relacionados.</p>
                </div>
                <button type="button"
                        class="px-3 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10"
                        data-atom-add-condition>
                    Agregar condicion
                </button>
            </div>

            @error('atom_conditions') <div class="mb-2 text-sm text-rose-300">{{ $message }}</div> @enderror

            <div class="overflow-x-auto rounded-xl border border-white/10">
                <table class="min-w-full text-sm">
                    <thead class="bg-white/5 text-white/70">
                        <tr>
                            <th class="text-left px-3 py-2">Campo Lead</th>
                            <th class="text-left px-3 py-2">Valor esperado</th>
                            <th class="text-left px-3 py-2">Webhook</th>
                            <th class="text-left px-3 py-2">Activa</th>
                            <th class="text-left px-3 py-2 w-24">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10 text-white/80" data-atom-conditions-body>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @php
        $letyBodyPlaceholder = <<<'TEXT'
name={{$lead->name}}
email={{$lead.email}}
phone={{$lead->phone}}
TEXT;

        $letyStoredWebhooks = isset($letyWebhooks)
            ? $letyWebhooks->map(fn ($webhook) => [
                'key' => (string) $webhook->id,
                'name' => $webhook->name,
                'url' => $webhook->url,
                'body' => $webhook->body,
                'order' => $webhook->order,
                'active' => $webhook->active ? 1 : 0,
            ])->values()->all()
            : [];

        $letyStoredConditions = isset($letyConditions)
            ? $letyConditions->map(fn ($condition) => [
                'lead_field' => $condition->lead_field,
                'expected_value' => $condition->expected_value,
                'webhook_key' => (string) $condition->lety_webhook_id,
                'order' => $condition->order,
                'active' => $condition->active ? 1 : 0,
            ])->values()->all()
            : [];

        $letyInitialWebhooks = collect(old('lety_webhooks', $letyStoredWebhooks))->values();
        $letyInitialConditions = collect(old('lety_conditions', $letyStoredConditions))->values();
    @endphp

    <div class="grid grid-cols-1 gap-4 hidden" data-show-for="lety" data-lety-block>
        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-white">Webhooks Lety</h3>
                    <p class="text-sm text-white/50">Configura endpoints y payloads form-urlencoded, un campo por linea.</p>
                </div>
                <button type="button"
                        class="px-3 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10"
                        data-lety-add-webhook>
                    Agregar webhook
                </button>
            </div>

            @error('lety_webhooks') <div class="mb-2 text-sm text-rose-300">{{ $message }}</div> @enderror

            <div class="overflow-x-auto rounded-xl border border-white/10">
                <table class="min-w-full text-sm">
                    <thead class="bg-white/5 text-white/70">
                        <tr>
                            <th class="text-left px-3 py-2">Nombre</th>
                            <th class="text-left px-3 py-2">URL</th>
                            <th class="text-left px-3 py-2">Payload form-urlencoded</th>
                            <th class="text-left px-3 py-2">Activo</th>
                            <th class="text-left px-3 py-2 w-24">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10 text-white/80" data-lety-webhooks-body>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-white">Condiciones Lety</h3>
                    <p class="text-sm text-white/50">Si varias condiciones coinciden, se envian todos los webhooks relacionados.</p>
                </div>
                <button type="button"
                        class="px-3 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10"
                        data-lety-add-condition>
                    Agregar condicion
                </button>
            </div>

            @error('lety_conditions') <div class="mb-2 text-sm text-rose-300">{{ $message }}</div> @enderror

            <div class="overflow-x-auto rounded-xl border border-white/10">
                <table class="min-w-full text-sm">
                    <thead class="bg-white/5 text-white/70">
                        <tr>
                            <th class="text-left px-3 py-2">Campo Lead</th>
                            <th class="text-left px-3 py-2">Valor esperado</th>
                            <th class="text-left px-3 py-2">Webhook</th>
                            <th class="text-left px-3 py-2">Activa</th>
                            <th class="text-left px-3 py-2 w-24">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10 text-white/80" data-lety-conditions-body>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 hidden" data-show-for="zoho">
        <div><label class="block mb-1 text-white/70">client_id</label><input name="client_id" value="{{ old('client_id', $integration->client_id ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">@error('client_id') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
        <div><label class="block mb-1 text-white/70">client_secret</label><input name="client_secret" value="{{ old('client_secret', $integration->client_secret ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">@error('client_secret') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
        <div><label class="block mb-1 text-white/70">code</label><input name="code" value="{{ old('code', $integration->code ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">@error('code') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
        <div><label class="block mb-1 text-white/70">access_token</label><input name="access_token" value="{{ old('access_token', $integration->tokent ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">@error('access_token') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
        <div><label class="block mb-1 text-white/70">refresh_token</label><input name="refresh_token" value="{{ old('refresh_token', $integration->refresh_token ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">@error('refresh_token') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
    </div>

    @php
        $freshworksStoredMappings = isset($freshworksVariableMappings)
            ? $freshworksVariableMappings->map(fn ($mapping) => [
                'target_variable' => $mapping->target_variable,
                'lead_field' => $mapping->lead_field,
                'expected_value' => $mapping->expected_value,
                'mapped_value' => $mapping->mapped_value,
                'order' => $mapping->order,
                'active' => $mapping->active ? 1 : 0,
            ])->values()->all()
            : [];

        $freshworksInitialMappings = collect(old('freshworks_variable_mappings', $freshworksStoredMappings))->values();
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 hidden" data-show-for="freshworks" data-freshworks-block>
        <div><label class="block mb-1 text-white/70">token *</label><input name="tokent" value="{{ old('tokent', $integration->tokent ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" data-required-for="freshworks">@error('tokent') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
        <div><label class="block mb-1 text-white/70">territory_id *</label><input name="territory_id" value="{{ old('territory_id', $integration->territory_id ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" data-required-for="freshworks">@error('territory_id') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
        <div><label class="block mb-1 text-white/70">owner_id *</label><input name="owner_id" value="{{ old('owner_id', $integration->owner_id ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" data-required-for="freshworks">@error('owner_id') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
        <div><label class="block mb-1 text-white/70">City *</label><input name="city" value="{{ old('city', $integration->city ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" data-required-for="freshworks">@error('city') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
        <div><label class="block mb-1 text-white/70">lead_source_id *</label><input name="lead_source_id" value="{{ old('lead_source_id', $integration->lead_source_id ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" data-required-for="freshworks">@error('lead_source_id') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
        <div class="md:col-span-2"><label class="block mb-1 text-white/70">custom_field *</label><textarea name="custom_field" rows="8" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 font-mono text-sm text-white" placeholder='json con los campos necesarios para crear el lead' data-required-for="freshworks">{{ old('custom_field', $integration->custom_field ?? '') }}</textarea>@error('custom_field') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>

        <div class="md:col-span-2 rounded-2xl border border-white/10 bg-white/5 p-4">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-white">Mapeo de variables</h3>
                    <p class="text-sm text-white/50">Normaliza valores del lead para variables del custom_field. Si no hay valor parametrizado, se usa el valor original.</p>
                </div>
                <button type="button"
                        class="px-3 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10"
                        data-freshworks-add-mapping>
                    Agregar variable
                </button>
            </div>

            @error('freshworks_variable_mappings') <div class="mb-2 text-sm text-rose-300">{{ $message }}</div> @enderror

            <div class="overflow-x-auto rounded-xl border border-white/10">
                <table class="min-w-full text-sm">
                    <thead class="bg-white/5 text-white/70">
                        <tr>
                            <th class="text-left px-3 py-2">Variable payload</th>
                            <th class="text-left px-3 py-2">Campo Lead</th>
                            <th class="text-left px-3 py-2">Valor esperado</th>
                            <th class="text-left px-3 py-2">Valor a enviar</th>
                            <th class="text-left px-3 py-2">Activa</th>
                            <th class="text-left px-3 py-2 w-24">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10 text-white/80" data-freshworks-mappings-body>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @php
        $integrationStoredMappings = isset($integrationVariableMappings)
            ? $integrationVariableMappings->map(fn ($mapping) => [
                'target_variable' => $mapping->target_variable,
                'lead_field' => $mapping->lead_field,
                'expected_value' => $mapping->expected_value,
                'mapped_value' => $mapping->mapped_value,
                'order' => $mapping->order,
                'active' => $mapping->active ? 1 : 0,
            ])->values()->all()
            : [];

        $integrationInitialMappings = collect(old('integration_variable_mappings', $integrationStoredMappings))->values();
    @endphp

    <div class="grid grid-cols-1 gap-4 hidden" data-show-for="atom zoho salesforce monday lety hubspot gohighlevel" data-integration-mappings-block>
        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-white">Mapeo de variables</h3>
                    <p class="text-sm text-white/50">Normaliza valores del lead para llaves del payload. Si no hay valor parametrizado, se usa el valor original.</p>
                </div>
                <button type="button"
                        class="px-3 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10"
                        data-integration-add-mapping>
                    Agregar variable
                </button>
            </div>

            @error('integration_variable_mappings') <div class="mb-2 text-sm text-rose-300">{{ $message }}</div> @enderror

            <div class="overflow-x-auto rounded-xl border border-white/10">
                <table class="min-w-full text-sm">
                    <thead class="bg-white/5 text-white/70">
                        <tr>
                            <th class="text-left px-3 py-2">Variable payload</th>
                            <th class="text-left px-3 py-2">Campo Lead</th>
                            <th class="text-left px-3 py-2">Valor esperado</th>
                            <th class="text-left px-3 py-2">Valor a enviar</th>
                            <th class="text-left px-3 py-2">Activa</th>
                            <th class="text-left px-3 py-2 w-24">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10 text-white/80" data-integration-mappings-body>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @php
        $gohighlevelBodyPlaceholder = <<<'JSON'
{
  "firstName": "{{$lead->firstName}}",
  "lastName": "{{$lead->lastName}}",
  "locationId": "fWWCKm54Zd8T0LLVW4kN",
  "email": "{{$lead->email}}",
  "phone": "{{$lead->phone}}",
  "source": "Meta Lead Ads",
  "tags": ["lead-web", "meta-lead"]
}
JSON;
    @endphp

    <div class="grid grid-cols-1 gap-4 hidden" data-show-for="gohighlevel">
        <div>
            <label class="block mb-1 text-white/70">Token LeadConnector / GoHighLevel *</label>
            <input name="tokent" type="password" value="{{ old('tokent', $integration->tokent ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" data-required-for="gohighlevel">
            @error('tokent') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
            <p class="mt-1 text-xs text-white/50">Se envia como Bearer token. El endpoint por defecto es contacts/upsert de LeadConnector si dejas la URL vacia.</p>
        </div>

        <div>
            <label class="block mb-1 text-white/70">Body JSON template *</label>
            <textarea name="body" rows="12" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 font-mono text-sm text-white" placeholder="{{ $gohighlevelBodyPlaceholder }}" data-required-for="gohighlevel">{{ old('body', $integration->body ?? '') }}</textarea>
            @error('body') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
            <p class="mt-1 text-xs text-white/50">Acepta variables simples del lead como <span class="font-mono">@{{ $lead->email }}</span>. Incluye <span class="font-mono">locationId</span> aqui si tu cuenta lo requiere.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 hidden" data-show-for="salesforce">
        <div><label class="block mb-1 text-white/70">url_credenciales *</label><input name="url_credenciales" value="{{ old('url_credenciales', $integration->url_credenciales ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" placeholder="https://..." data-required-for="salesforce">@error('url_credenciales') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
        <div><label class="block mb-1 text-white/70">Client ID / Consumer Key *</label><input name="username" value="{{ old('username', $integration->username ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" data-required-for="salesforce">@error('username') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
        <div><label class="block mb-1 text-white/70">Client Secret / Consumer Secret *</label><input name="password" type="password" value="{{ old('password', $integration->password ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" data-required-for="salesforce">@error('password') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
        <div><label class="block mb-1 text-white/70">token</label><input name="tokent" value="{{ old('tokent', $integration->tokent ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" readonly>@error('tokent') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
        <div class="md:col-span-2"><label class="block mb-1 text-white/70">body *</label><textarea name="body" rows="10" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 font-mono text-sm text-white" placeholder='json con el payload a enviar a Salesforce' data-required-for="salesforce">{{ old('body', $integration->body ?? '') }}</textarea>@error('body') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
    </div>

    <div class="grid grid-cols-1 gap-4 hidden" data-show-for="monday">
        <div>
            <label class="block mb-1 text-white/70">Authorization *</label>
            <input name="tokent" value="{{ old('tokent', $integration->tokent ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" placeholder="Token permanente de Monday" data-required-for="monday">
            @error('tokent') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
            <p class="mt-1 text-xs text-white/50">Se usara como header Authorization para las consultas GraphQL a Monday.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 hidden" data-show-for="hubspot">
        <div>
            <label class="block mb-1 text-white/70">access_token *</label>
            <input name="access_token" value="{{ old('access_token', $integration->tokent ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" data-required-for="hubspot">
            @error('access_token') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
            @error('tokent') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="block mb-1 text-white/70">url_consulta_lead *</label>
            <input name="url_consulta_lead" value="{{ old('url_consulta_lead', $integration->url_consulta_lead ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" placeholder="https://..." data-required-for="hubspot">
            @error('url_consulta_lead') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="block mb-1 text-white/70">url_negocio *</label>
            <input name="url_negocio" value="{{ old('url_negocio', $integration->url_negocio ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" placeholder="https://..." data-required-for="hubspot">
            @error('url_negocio') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="block mb-1 text-white/70">url_creacionlead *</label>
            <input name="url_creacionlead" value="{{ old('url_creacionlead', $integration->url_creacionlead ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" placeholder="https://..." data-required-for="hubspot">
            @error('url_creacionlead') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
        </div>
        <div class="md:col-span-2">
            <label class="block mb-1 text-white/70">dealname *</label>
            <input name="dealname" value="{{ old('dealname', $integration->dealname ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" placeholder="Quiero comprar variable soy variavle" data-required-for="hubspot">
            @error('dealname') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
            <p class="mt-1 text-xs text-white/50">Acepta variables dinámicas<span class="font-mono"></span></p>
        </div>
        <div>
            <label class="block mb-1 text-white/70">dealstage *</label>
            <input name="dealstage" value="{{ old('dealstage', $integration->dealstage ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" data-required-for="hubspot">
            @error('dealstage') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
        </div>
        <div class="md:col-span-2">
            <label class="block mb-1 text-white/70">body *</label>
            <textarea name="body" rows="10" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 font-mono text-sm text-white" placeholder='properties email variable,firstname' data-required-for="hubspot">{{ old('body', $integration->body ?? '') }}</textarea>
            @error('body') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
            <p class="mt-1 text-xs text-white/50">Debe ser JSON válido y acepta variables dinámicas<span class="font-mono"></span>.</p>
        </div>
    </div>

    @if(isset($integration) && $integration->exists)
        <div class="rounded-2xl border border-white/10 bg-white/5 p-4 space-y-3">
            <div class="text-sm text-white/70">
                <div class="mb-1 font-semibold">Public Key</div>
                <div class="rounded-xl border border-white/10 bg-slate-900/60 p-3 font-mono text-xs break-all text-white/80">{{ $integration->public_key }}</div>
            </div>
            <x-toggle-switch name="regenerate_public_key" value="1" label="Regenerar public_key al guardar" />
        </div>
    @endif

    <div class="flex gap-2 pt-2">
        <button class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10" type="submit">Guardar</button>
        <a href="{{ route('integrations.index') }}" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">Cancelar</a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const typeSelect = document.getElementById('integrationtype_id');
  const conditionalBlocks = document.querySelectorAll('[data-show-for]');
  const crmPrefixToggle = document.querySelector('[data-crm-prefix-toggle]');
  const crmPrefixInput = document.querySelector('[data-crm-prefix-input]');
  const baseUrlBlock = document.querySelector('[data-base-url-block]');
  const baseUrlInput = document.querySelector('[data-base-url-input]');
  const kommoPipelineBlock = document.querySelector('[data-kommo-pipeline-block]');
  const kommoConditionsBody = document.querySelector('[data-kommo-conditions-body]');
  const kommoAddConditionButton = document.querySelector('[data-kommo-add-condition]');
  const atomBlock = document.querySelector('[data-atom-block]');
  const atomWebhooksBody = document.querySelector('[data-atom-webhooks-body]');
  const atomConditionsBody = document.querySelector('[data-atom-conditions-body]');
  const atomAddWebhookButton = document.querySelector('[data-atom-add-webhook]');
  const atomAddConditionButton = document.querySelector('[data-atom-add-condition]');
  const letyBlock = document.querySelector('[data-lety-block]');
  const letyWebhooksBody = document.querySelector('[data-lety-webhooks-body]');
  const letyConditionsBody = document.querySelector('[data-lety-conditions-body]');
  const letyAddWebhookButton = document.querySelector('[data-lety-add-webhook]');
  const letyAddConditionButton = document.querySelector('[data-lety-add-condition]');
  const freshworksBlock = document.querySelector('[data-freshworks-block]');
  const freshworksMappingsBody = document.querySelector('[data-freshworks-mappings-body]');
  const freshworksAddMappingButton = document.querySelector('[data-freshworks-add-mapping]');
  const integrationMappingsBlock = document.querySelector('[data-integration-mappings-block]');
  const integrationMappingsBody = document.querySelector('[data-integration-mappings-body]');
  const integrationAddMappingButton = document.querySelector('[data-integration-add-mapping]');
  const leadFields = @json(array_values($leadFields ?? []));
  const initialKommoConditions = @json($kommoPipelineInitialConditions ?? []);
  const initialAtomWebhooks = @json($atomInitialWebhooks ?? []);
  const initialAtomConditions = @json($atomInitialConditions ?? []);
  const initialLetyWebhooks = @json($letyInitialWebhooks ?? []);
  const initialLetyConditions = @json($letyInitialConditions ?? []);
  const initialFreshworksMappings = @json($freshworksInitialMappings ?? []);
  const initialIntegrationMappings = @json($integrationInitialMappings ?? []);
  const letyBodyPlaceholder = @json($letyBodyPlaceholder ?? '');
  const kommoPipelinesUrl = @json(($integration->exists ?? false) ? route('integrations.kommo-pipeline.pipelines', $integration) : null);
  const kommoStatusesUrlTemplate = @json(($integration->exists ?? false) ? route('integrations.kommo-pipeline.statuses', [$integration, '__PIPELINE_ID__']) : null);
  let kommoPipelinesCache = null;
  let kommoConditionIndex = 0;
  let atomWebhookIndex = 0;
  let atomConditionIndex = 0;
  let letyWebhookIndex = 0;
  let letyConditionIndex = 0;
  let freshworksMappingIndex = 0;
  let integrationMappingIndex = 0;

  function normalizeTypeKey(raw) {
    const key = (raw || '').trim().toLowerCase();
    if (key.includes('google')) return 'google_sheets';
    if (key.includes('kommopipeline') || key.includes('kommo_pipeline') || key.includes('kommo pipeline')) return 'kommopipeline';
    if (key.includes('kommo')) return 'kommo';
    if (key.includes('atom')) return 'atom';
    if (key.includes('lety')) return 'lety';
    if (key.includes('zoho')) return 'zoho';
    if (key.includes('freshworks')) return 'freshworks';
    if (key.includes('salesforce')) return 'salesforce';
    if (key.includes('monday')) return 'monday';
    if (key.includes('hubspot')) return 'hubspot';
    if (key.includes('gohighlevel') || key.includes('go_high_level') || key.includes('leadconnector') || key.includes('lead_connector')) return 'gohighlevel';
    return key;
  }

  function getSelectedKey() {
    const opt = typeSelect.options[typeSelect.selectedIndex];
    const byData = normalizeTypeKey(opt?.dataset?.key || '');
    if (byData) return byData;
    return normalizeTypeKey(opt?.textContent || '');
  }

  function setBlockVisible(block, visible, key) {
    block.classList.toggle('hidden', !visible);

    block.querySelectorAll('input, select, textarea').forEach(el => {
      el.disabled = !visible;
      const requiredFor = (el.dataset.requiredFor || '').trim().toLowerCase();
      el.required = visible && requiredFor !== '' && requiredFor === key;
    });
  }

  function refreshCrmPrefixRequirement() {
    if (!crmPrefixInput || !crmPrefixToggle) return;
    const key = getSelectedKey();
    const supportsCustomPrefix = key === 'kommo' || key === 'kommopipeline' || key === 'freshworks' || key === 'hubspot' || key === 'gohighlevel';
    const isEnabled = supportsCustomPrefix && crmPrefixToggle.checked;

    crmPrefixInput.disabled = !isEnabled;
    crmPrefixInput.required = isEnabled;
  }

  function refresh() {
    const key = getSelectedKey();

    conditionalBlocks.forEach(block => {
      const showFor = (block.dataset.showFor || '').split(/\s+/).map(s => s.trim().toLowerCase()).filter(Boolean);
      const shouldShow = key && showFor.includes(key);
      setBlockVisible(block, shouldShow, key);
    });

    if (baseUrlBlock && baseUrlInput) {
      const shouldHideBaseUrl = key === 'hubspot' || key === 'atom' || key === 'lety';
      const shouldRequireBaseUrl = key !== 'hubspot' && key !== 'gohighlevel' && key !== 'atom' && key !== 'lety';
      baseUrlBlock.classList.toggle('hidden', shouldHideBaseUrl);
      baseUrlInput.disabled = shouldHideBaseUrl;
      baseUrlInput.required = shouldRequireBaseUrl;
      baseUrlInput.placeholder = key === 'gohighlevel'
        ? 'https://services.leadconnectorhq.com/contacts/upsert'
        : (key === 'kommopipeline' ? 'https://tudominio.kommo.com' : 'https://...');
    }

    refreshCrmPrefixRequirement();
  }

  function option(value, label, selected = false) {
    const node = document.createElement('option');
    node.value = value || '';
    node.textContent = label || value || 'Seleccione...';
    node.selected = selected;
    return node;
  }

  async function fetchKommoPipelines() {
    if (kommoPipelinesCache) return kommoPipelinesCache;
    if (!kommoPipelinesUrl) return [];

    const response = await fetch(kommoPipelinesUrl, { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error(await response.text());
    kommoPipelinesCache = await response.json();
    return kommoPipelinesCache;
  }

  async function fetchKommoStatuses(pipelineId) {
    if (!kommoStatusesUrlTemplate || !pipelineId) return [];
    const url = kommoStatusesUrlTemplate.replace('__PIPELINE_ID__', encodeURIComponent(pipelineId));
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error(await response.text());
    return await response.json();
  }

  function selectedOptionText(select) {
    return select.options[select.selectedIndex]?.textContent || '';
  }

  async function populatePipelineSelect(select, selectedId = '', selectedName = '') {
    select.innerHTML = '';
    select.appendChild(option('', kommoPipelinesUrl ? 'Seleccione...' : 'Guarda URL y token primero'));

    if (!kommoPipelinesUrl) {
      if (selectedId) select.appendChild(option(selectedId, selectedName || selectedId, true));
      return;
    }

    try {
      const pipelines = await fetchKommoPipelines();
      pipelines.forEach(pipeline => select.appendChild(option(pipeline.id, pipeline.name, String(pipeline.id) === String(selectedId))));

      if (selectedId && !pipelines.some(pipeline => String(pipeline.id) === String(selectedId))) {
        select.appendChild(option(selectedId, selectedName || selectedId, true));
      }
    } catch (error) {
      select.appendChild(option(selectedId, selectedName || 'No fue posible consultar Kommo', true));
    }
  }

  async function populateStatusSelect(select, pipelineId, selectedId = '', selectedName = '') {
    select.innerHTML = '';
    select.appendChild(option('', pipelineId ? 'Seleccione...' : 'Seleccione un pipeline...'));

    if (!pipelineId || !kommoStatusesUrlTemplate) {
      if (selectedId) select.appendChild(option(selectedId, selectedName || selectedId, true));
      return;
    }

    try {
      const statuses = await fetchKommoStatuses(pipelineId);
      statuses.forEach(status => select.appendChild(option(status.id, status.name, String(status.id) === String(selectedId))));

      if (selectedId && !statuses.some(status => String(status.id) === String(selectedId))) {
        select.appendChild(option(selectedId, selectedName || selectedId, true));
      }
    } catch (error) {
      select.appendChild(option(selectedId, selectedName || 'No fue posible consultar statuses', true));
    }
  }

  function leadFieldSelect(name, selected) {
    const select = document.createElement('select');
    select.name = name;
    select.className = 'w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white';
    select.appendChild(option('', 'Seleccione...'));
    leadFields.forEach(field => select.appendChild(option(field, field, field === selected)));
    return select;
  }

  function textInput(name, value, placeholder = '') {
    const input = document.createElement('input');
    input.name = name;
    input.value = value ?? '';
    input.placeholder = placeholder;
    input.className = 'w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white';
    return input;
  }

  function textareaInput(name, value, placeholder = '') {
    const textarea = document.createElement('textarea');
    textarea.name = name;
    textarea.value = value ?? '';
    textarea.placeholder = placeholder;
    textarea.rows = 8;
    textarea.className = 'w-full min-w-72 rounded-xl border border-white/10 bg-slate-900/60 p-2 font-mono text-xs text-white';
    return textarea;
  }

  function hiddenInput(name, value = '') {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value ?? '';
    return input;
  }

  function cell(child) {
    const td = document.createElement('td');
    td.className = 'px-3 py-2 align-top';
    td.appendChild(child);
    return td;
  }

  function addKommoCondition(condition = {}) {
    if (!kommoConditionsBody) return;

    const index = kommoConditionIndex++;
    const row = document.createElement('tr');
    row.className = 'hover:bg-white/5';

    row.appendChild(cell(leadFieldSelect(`kommo_pipeline_conditions[${index}][lead_field]`, condition.lead_field || '')));
    row.appendChild(cell(textInput(`kommo_pipeline_conditions[${index}][expected_value]`, condition.expected_value || '', 'Ej: Bogota')));

    const pipelineWrap = document.createElement('div');
    const pipelineName = hiddenInput(`kommo_pipeline_conditions[${index}][pipeline_name]`, condition.pipeline_name || '');
    const pipelineSelect = document.createElement('select');
    pipelineSelect.name = `kommo_pipeline_conditions[${index}][pipeline_id]`;
    pipelineSelect.className = 'w-full min-w-48 rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white';
    pipelineWrap.appendChild(pipelineName);
    pipelineWrap.appendChild(pipelineSelect);
    row.appendChild(cell(pipelineWrap));

    const statusWrap = document.createElement('div');
    const statusName = hiddenInput(`kommo_pipeline_conditions[${index}][status_name]`, condition.status_name || '');
    const statusSelect = document.createElement('select');
    statusSelect.name = `kommo_pipeline_conditions[${index}][status_id]`;
    statusSelect.className = 'w-full min-w-48 rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white';
    statusWrap.appendChild(statusName);
    statusWrap.appendChild(statusSelect);
    row.appendChild(cell(statusWrap));

    const activeWrap = document.createElement('label');
    activeWrap.className = 'inline-flex items-center gap-2';
    activeWrap.appendChild(hiddenInput(`kommo_pipeline_conditions[${index}][active]`, '0'));
    const active = document.createElement('input');
    active.type = 'checkbox';
    active.name = `kommo_pipeline_conditions[${index}][active]`;
    active.value = '1';
    active.checked = String(condition.active ?? '1') !== '0';
    active.className = 'rounded border-white/10 bg-slate-900/60';
    activeWrap.appendChild(active);
    activeWrap.appendChild(document.createTextNode('Si'));
    row.appendChild(cell(activeWrap));

    const actions = document.createElement('td');
    actions.className = 'px-3 py-2 align-top';
    actions.appendChild(hiddenInput(`kommo_pipeline_conditions[${index}][order]`, condition.order ?? index));
    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'px-3 py-1.5 rounded-lg bg-rose-500/20 hover:bg-rose-500/30 border border-rose-300/20 text-white';
    remove.textContent = 'Quitar';
    remove.addEventListener('click', () => row.remove());
    actions.appendChild(remove);
    row.appendChild(actions);

    kommoConditionsBody.appendChild(row);

    populatePipelineSelect(pipelineSelect, condition.pipeline_id || '', condition.pipeline_name || '').then(() => {
      pipelineName.value = selectedOptionText(pipelineSelect);
    });
    populateStatusSelect(statusSelect, condition.pipeline_id || '', condition.status_id || '', condition.status_name || '').then(() => {
      statusName.value = selectedOptionText(statusSelect);
    });

    pipelineSelect.addEventListener('change', async () => {
      pipelineName.value = selectedOptionText(pipelineSelect);
      statusName.value = '';
      await populateStatusSelect(statusSelect, pipelineSelect.value);
    });

    statusSelect.addEventListener('change', () => {
      statusName.value = selectedOptionText(statusSelect);
    });
  }

  function currentAtomWebhooks() {
    if (!atomWebhooksBody) return [];

    return Array.from(atomWebhooksBody.querySelectorAll('[data-atom-webhook-row]')).map(row => {
      const nameInput = row.querySelector('[data-atom-webhook-name]');
      const urlInput = row.querySelector('[data-atom-webhook-url]');
      return {
        key: row.dataset.atomWebhookKey || '',
        name: nameInput?.value || urlInput?.value || 'Webhook',
      };
    }).filter(webhook => webhook.key !== '');
  }

  function atomWebhookSelect(name, selected) {
    const select = document.createElement('select');
    select.name = name;
    select.className = 'w-full min-w-48 rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white';
    select.dataset.atomWebhookSelect = '1';
    select.dataset.selectedWebhook = selected || '';
    return select;
  }

  function refreshAtomWebhookSelects() {
    const webhooks = currentAtomWebhooks();

    document.querySelectorAll('[data-atom-webhook-select]').forEach(select => {
      const selected = select.value || select.dataset.selectedWebhook || '';
      select.innerHTML = '';
      select.appendChild(option('', webhooks.length > 0 ? 'Seleccione...' : 'Agrega un webhook primero'));

      webhooks.forEach(webhook => {
        select.appendChild(option(webhook.key, webhook.name, String(webhook.key) === String(selected)));
      });

      if (selected && !webhooks.some(webhook => String(webhook.key) === String(selected))) {
        select.appendChild(option(selected, selected, true));
      }

      select.dataset.selectedWebhook = select.value;
    });
  }

  function addAtomWebhook(webhook = {}) {
    if (!atomWebhooksBody) return;

    const index = atomWebhookIndex++;
    const key = webhook.key || `new_${index}`;
    const row = document.createElement('tr');
    row.className = 'hover:bg-white/5';
    row.dataset.atomWebhookRow = '1';
    row.dataset.atomWebhookKey = key;

    const nameWrap = document.createElement('div');
    nameWrap.appendChild(hiddenInput(`atom_webhooks[${index}][key]`, key));
    const nameInput = textInput(`atom_webhooks[${index}][name]`, webhook.name || '', 'Ej: CRM principal');
    nameInput.dataset.atomWebhookName = '1';
    nameInput.addEventListener('input', refreshAtomWebhookSelects);
    nameWrap.appendChild(nameInput);
    row.appendChild(cell(nameWrap));

    const urlInput = textInput(`atom_webhooks[${index}][url]`, webhook.url || '', 'https://...');
    urlInput.dataset.atomWebhookUrl = '1';
    row.appendChild(cell(urlInput));

    const defaultWrap = document.createElement('label');
    defaultWrap.className = 'inline-flex items-center gap-2';
    defaultWrap.appendChild(hiddenInput(`atom_webhooks[${index}][is_default]`, '0'));
    const isDefault = document.createElement('input');
    isDefault.type = 'checkbox';
    isDefault.name = `atom_webhooks[${index}][is_default]`;
    isDefault.value = '1';
    isDefault.checked = String(webhook.is_default ?? '0') !== '0';
    isDefault.className = 'rounded border-white/10 bg-slate-900/60';
    isDefault.dataset.atomDefaultCheckbox = '1';
    isDefault.addEventListener('change', () => {
      if (!isDefault.checked) return;
      document.querySelectorAll('[data-atom-default-checkbox]').forEach(other => {
        if (other !== isDefault) other.checked = false;
      });
    });
    defaultWrap.appendChild(isDefault);
    defaultWrap.appendChild(document.createTextNode('Si'));
    row.appendChild(cell(defaultWrap));

    const activeWrap = document.createElement('label');
    activeWrap.className = 'inline-flex items-center gap-2';
    activeWrap.appendChild(hiddenInput(`atom_webhooks[${index}][active]`, '0'));
    const active = document.createElement('input');
    active.type = 'checkbox';
    active.name = `atom_webhooks[${index}][active]`;
    active.value = '1';
    active.checked = String(webhook.active ?? '1') !== '0';
    active.className = 'rounded border-white/10 bg-slate-900/60';
    activeWrap.appendChild(active);
    activeWrap.appendChild(document.createTextNode('Si'));
    row.appendChild(cell(activeWrap));

    const actions = document.createElement('td');
    actions.className = 'px-3 py-2 align-top';
    actions.appendChild(hiddenInput(`atom_webhooks[${index}][order]`, webhook.order ?? index));
    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'px-3 py-1.5 rounded-lg bg-rose-500/20 hover:bg-rose-500/30 border border-rose-300/20 text-white';
    remove.textContent = 'Quitar';
    remove.addEventListener('click', () => {
      row.remove();
      refreshAtomWebhookSelects();
    });
    actions.appendChild(remove);
    row.appendChild(actions);

    atomWebhooksBody.appendChild(row);
    refreshAtomWebhookSelects();
  }

  function addAtomCondition(condition = {}) {
    if (!atomConditionsBody) return;

    const index = atomConditionIndex++;
    const row = document.createElement('tr');
    row.className = 'hover:bg-white/5';

    row.appendChild(cell(leadFieldSelect(`atom_conditions[${index}][lead_field]`, condition.lead_field || '')));
    row.appendChild(cell(textInput(`atom_conditions[${index}][expected_value]`, condition.expected_value || '', 'Ej: Bogota')));
    row.appendChild(cell(atomWebhookSelect(`atom_conditions[${index}][webhook_key]`, condition.webhook_key || '')));

    const activeWrap = document.createElement('label');
    activeWrap.className = 'inline-flex items-center gap-2';
    activeWrap.appendChild(hiddenInput(`atom_conditions[${index}][active]`, '0'));
    const active = document.createElement('input');
    active.type = 'checkbox';
    active.name = `atom_conditions[${index}][active]`;
    active.value = '1';
    active.checked = String(condition.active ?? '1') !== '0';
    active.className = 'rounded border-white/10 bg-slate-900/60';
    activeWrap.appendChild(active);
    activeWrap.appendChild(document.createTextNode('Si'));
    row.appendChild(cell(activeWrap));

    const actions = document.createElement('td');
    actions.className = 'px-3 py-2 align-top';
    actions.appendChild(hiddenInput(`atom_conditions[${index}][order]`, condition.order ?? index));
    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'px-3 py-1.5 rounded-lg bg-rose-500/20 hover:bg-rose-500/30 border border-rose-300/20 text-white';
    remove.textContent = 'Quitar';
    remove.addEventListener('click', () => row.remove());
    actions.appendChild(remove);
    row.appendChild(actions);

    atomConditionsBody.appendChild(row);
    refreshAtomWebhookSelects();
  }

  function bootAtom() {
    if (!atomBlock) return;

    if (initialAtomWebhooks.length > 0) {
      initialAtomWebhooks.forEach(webhook => addAtomWebhook(webhook));
    } else {
      addAtomWebhook({ is_default: 1 });
    }

    if (initialAtomConditions.length > 0) {
      initialAtomConditions.forEach(condition => addAtomCondition(condition));
    } else {
      addAtomCondition();
    }
  }

  function currentLetyWebhooks() {
    if (!letyWebhooksBody) return [];

    return Array.from(letyWebhooksBody.querySelectorAll('[data-lety-webhook-row]')).map(row => {
      const nameInput = row.querySelector('[data-lety-webhook-name]');
      const urlInput = row.querySelector('[data-lety-webhook-url]');
      return {
        key: row.dataset.letyWebhookKey || '',
        name: nameInput?.value || urlInput?.value || 'Webhook',
      };
    }).filter(webhook => webhook.key !== '');
  }

  function letyWebhookSelect(name, selected) {
    const select = document.createElement('select');
    select.name = name;
    select.className = 'w-full min-w-48 rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white';
    select.dataset.letyWebhookSelect = '1';
    select.dataset.selectedWebhook = selected || '';
    return select;
  }

  function refreshLetyWebhookSelects() {
    const webhooks = currentLetyWebhooks();

    document.querySelectorAll('[data-lety-webhook-select]').forEach(select => {
      const selected = select.value || select.dataset.selectedWebhook || '';
      select.innerHTML = '';
      select.appendChild(option('', webhooks.length > 0 ? 'Seleccione...' : 'Agrega un webhook primero'));

      webhooks.forEach(webhook => {
        select.appendChild(option(webhook.key, webhook.name, String(webhook.key) === String(selected)));
      });

      if (selected && !webhooks.some(webhook => String(webhook.key) === String(selected))) {
        select.appendChild(option(selected, selected, true));
      }

      select.dataset.selectedWebhook = select.value;
    });
  }

  function addLetyWebhook(webhook = {}) {
    if (!letyWebhooksBody) return;

    const index = letyWebhookIndex++;
    const key = webhook.key || `new_${index}`;
    const row = document.createElement('tr');
    row.className = 'hover:bg-white/5';
    row.dataset.letyWebhookRow = '1';
    row.dataset.letyWebhookKey = key;

    const nameWrap = document.createElement('div');
    nameWrap.appendChild(hiddenInput(`lety_webhooks[${index}][key]`, key));
    const nameInput = textInput(`lety_webhooks[${index}][name]`, webhook.name || '', 'Ej: Lety principal');
    nameInput.dataset.letyWebhookName = '1';
    nameInput.addEventListener('input', refreshLetyWebhookSelects);
    nameWrap.appendChild(nameInput);
    row.appendChild(cell(nameWrap));

    const urlInput = textInput(`lety_webhooks[${index}][url]`, webhook.url || '', 'https://...');
    urlInput.dataset.letyWebhookUrl = '1';
    row.appendChild(cell(urlInput));

    row.appendChild(cell(textareaInput(`lety_webhooks[${index}][body]`, webhook.body || letyBodyPlaceholder, letyBodyPlaceholder)));

    const activeWrap = document.createElement('label');
    activeWrap.className = 'inline-flex items-center gap-2';
    activeWrap.appendChild(hiddenInput(`lety_webhooks[${index}][active]`, '0'));
    const active = document.createElement('input');
    active.type = 'checkbox';
    active.name = `lety_webhooks[${index}][active]`;
    active.value = '1';
    active.checked = String(webhook.active ?? '1') !== '0';
    active.className = 'rounded border-white/10 bg-slate-900/60';
    activeWrap.appendChild(active);
    activeWrap.appendChild(document.createTextNode('Si'));
    row.appendChild(cell(activeWrap));

    const actions = document.createElement('td');
    actions.className = 'px-3 py-2 align-top';
    actions.appendChild(hiddenInput(`lety_webhooks[${index}][order]`, webhook.order ?? index));
    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'px-3 py-1.5 rounded-lg bg-rose-500/20 hover:bg-rose-500/30 border border-rose-300/20 text-white';
    remove.textContent = 'Quitar';
    remove.addEventListener('click', () => {
      row.remove();
      refreshLetyWebhookSelects();
    });
    actions.appendChild(remove);
    row.appendChild(actions);

    letyWebhooksBody.appendChild(row);
    refreshLetyWebhookSelects();
  }

  function addLetyCondition(condition = {}) {
    if (!letyConditionsBody) return;

    const index = letyConditionIndex++;
    const row = document.createElement('tr');
    row.className = 'hover:bg-white/5';

    row.appendChild(cell(leadFieldSelect(`lety_conditions[${index}][lead_field]`, condition.lead_field || '')));
    row.appendChild(cell(textInput(`lety_conditions[${index}][expected_value]`, condition.expected_value || '', 'Ej: Bogota')));
    row.appendChild(cell(letyWebhookSelect(`lety_conditions[${index}][webhook_key]`, condition.webhook_key || '')));

    const activeWrap = document.createElement('label');
    activeWrap.className = 'inline-flex items-center gap-2';
    activeWrap.appendChild(hiddenInput(`lety_conditions[${index}][active]`, '0'));
    const active = document.createElement('input');
    active.type = 'checkbox';
    active.name = `lety_conditions[${index}][active]`;
    active.value = '1';
    active.checked = String(condition.active ?? '1') !== '0';
    active.className = 'rounded border-white/10 bg-slate-900/60';
    activeWrap.appendChild(active);
    activeWrap.appendChild(document.createTextNode('Si'));
    row.appendChild(cell(activeWrap));

    const actions = document.createElement('td');
    actions.className = 'px-3 py-2 align-top';
    actions.appendChild(hiddenInput(`lety_conditions[${index}][order]`, condition.order ?? index));
    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'px-3 py-1.5 rounded-lg bg-rose-500/20 hover:bg-rose-500/30 border border-rose-300/20 text-white';
    remove.textContent = 'Quitar';
    remove.addEventListener('click', () => row.remove());
    actions.appendChild(remove);
    row.appendChild(actions);

    letyConditionsBody.appendChild(row);
    refreshLetyWebhookSelects();
  }

  function bootLety() {
    if (!letyBlock) return;

    if (initialLetyWebhooks.length > 0) {
      initialLetyWebhooks.forEach(webhook => addLetyWebhook(webhook));
    } else {
      addLetyWebhook({ body: letyBodyPlaceholder });
    }

    if (initialLetyConditions.length > 0) {
      initialLetyConditions.forEach(condition => addLetyCondition(condition));
    } else {
      addLetyCondition();
    }
  }

  function addFreshworksMapping(mapping = {}) {
    if (!freshworksMappingsBody) return;

    const index = freshworksMappingIndex++;
    const row = document.createElement('tr');
    row.className = 'hover:bg-white/5';

    row.appendChild(cell(textInput(`freshworks_variable_mappings[${index}][target_variable]`, mapping.target_variable || '', 'Ej: variable1')));
    row.appendChild(cell(leadFieldSelect(`freshworks_variable_mappings[${index}][lead_field]`, mapping.lead_field || '')));
    row.appendChild(cell(textInput(`freshworks_variable_mappings[${index}][expected_value]`, mapping.expected_value || '', 'Ej: medellin')));
    row.appendChild(cell(textInput(`freshworks_variable_mappings[${index}][mapped_value]`, mapping.mapped_value ?? '', 'Ej: Medellin')));

    const activeWrap = document.createElement('label');
    activeWrap.className = 'inline-flex items-center gap-2';
    activeWrap.appendChild(hiddenInput(`freshworks_variable_mappings[${index}][active]`, '0'));
    const active = document.createElement('input');
    active.type = 'checkbox';
    active.name = `freshworks_variable_mappings[${index}][active]`;
    active.value = '1';
    active.checked = String(mapping.active ?? '1') !== '0';
    active.className = 'rounded border-white/10 bg-slate-900/60';
    activeWrap.appendChild(active);
    activeWrap.appendChild(document.createTextNode('Si'));
    row.appendChild(cell(activeWrap));

    const actions = document.createElement('td');
    actions.className = 'px-3 py-2 align-top';
    actions.appendChild(hiddenInput(`freshworks_variable_mappings[${index}][order]`, mapping.order ?? index));
    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'px-3 py-1.5 rounded-lg bg-rose-500/20 hover:bg-rose-500/30 border border-rose-300/20 text-white';
    remove.textContent = 'Quitar';
    remove.addEventListener('click', () => row.remove());
    actions.appendChild(remove);
    row.appendChild(actions);

    freshworksMappingsBody.appendChild(row);
  }

  function bootFreshworks() {
    if (!freshworksBlock) return;

    if (initialFreshworksMappings.length > 0) {
      initialFreshworksMappings.forEach(mapping => addFreshworksMapping(mapping));
    }
  }

  function addIntegrationMapping(mapping = {}) {
    if (!integrationMappingsBody) return;

    const index = integrationMappingIndex++;
    const row = document.createElement('tr');
    row.className = 'hover:bg-white/5';

    row.appendChild(cell(textInput(`integration_variable_mappings[${index}][target_variable]`, mapping.target_variable || '', 'Ej: city')));
    row.appendChild(cell(leadFieldSelect(`integration_variable_mappings[${index}][lead_field]`, mapping.lead_field || '')));
    row.appendChild(cell(textInput(`integration_variable_mappings[${index}][expected_value]`, mapping.expected_value || '', 'Ej: medellin')));
    row.appendChild(cell(textInput(`integration_variable_mappings[${index}][mapped_value]`, mapping.mapped_value ?? '', 'Ej: Medellin')));

    const activeWrap = document.createElement('label');
    activeWrap.className = 'inline-flex items-center gap-2';
    activeWrap.appendChild(hiddenInput(`integration_variable_mappings[${index}][active]`, '0'));
    const active = document.createElement('input');
    active.type = 'checkbox';
    active.name = `integration_variable_mappings[${index}][active]`;
    active.value = '1';
    active.checked = String(mapping.active ?? '1') !== '0';
    active.className = 'rounded border-white/10 bg-slate-900/60';
    activeWrap.appendChild(active);
    activeWrap.appendChild(document.createTextNode('Si'));
    row.appendChild(cell(activeWrap));

    const actions = document.createElement('td');
    actions.className = 'px-3 py-2 align-top';
    actions.appendChild(hiddenInput(`integration_variable_mappings[${index}][order]`, mapping.order ?? index));
    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'px-3 py-1.5 rounded-lg bg-rose-500/20 hover:bg-rose-500/30 border border-rose-300/20 text-white';
    remove.textContent = 'Quitar';
    remove.addEventListener('click', () => row.remove());
    actions.appendChild(remove);
    row.appendChild(actions);

    integrationMappingsBody.appendChild(row);
  }

  function bootIntegrationMappings() {
    if (!integrationMappingsBlock) return;

    if (initialIntegrationMappings.length > 0) {
      initialIntegrationMappings.forEach(mapping => addIntegrationMapping(mapping));
    }
  }

  function bootKommoPipeline() {
    if (!kommoPipelineBlock) return;

    const defaultPipeline = kommoPipelineBlock.querySelector('[data-kommo-default-pipeline]');
    const defaultStatus = kommoPipelineBlock.querySelector('[data-kommo-default-status]');
    const defaultPipelineName = kommoPipelineBlock.querySelector('[data-kommo-default-pipeline-name]');
    const defaultStatusName = kommoPipelineBlock.querySelector('[data-kommo-default-status-name]');

    if (defaultPipeline && defaultStatus) {
      populatePipelineSelect(defaultPipeline, defaultPipeline.dataset.selectedId || '', defaultPipeline.dataset.selectedName || '').then(() => {
        defaultPipelineName.value = selectedOptionText(defaultPipeline);
      });
      populateStatusSelect(defaultStatus, defaultPipeline.dataset.selectedId || '', defaultStatus.dataset.selectedId || '', defaultStatus.dataset.selectedName || '').then(() => {
        defaultStatusName.value = selectedOptionText(defaultStatus);
      });

      defaultPipeline.addEventListener('change', async () => {
        defaultPipelineName.value = selectedOptionText(defaultPipeline);
        defaultStatusName.value = '';
        await populateStatusSelect(defaultStatus, defaultPipeline.value);
      });

      defaultStatus.addEventListener('change', () => {
        defaultStatusName.value = selectedOptionText(defaultStatus);
      });
    }

    if (initialKommoConditions.length > 0) {
      initialKommoConditions.forEach(condition => addKommoCondition(condition));
    }
  }

  if (typeSelect) typeSelect.addEventListener('change', refresh);
  if (crmPrefixToggle) crmPrefixToggle.addEventListener('change', refreshCrmPrefixRequirement);
  if (kommoAddConditionButton) kommoAddConditionButton.addEventListener('click', () => addKommoCondition());
  if (atomAddWebhookButton) atomAddWebhookButton.addEventListener('click', () => addAtomWebhook());
  if (atomAddConditionButton) atomAddConditionButton.addEventListener('click', () => addAtomCondition());
  if (letyAddWebhookButton) letyAddWebhookButton.addEventListener('click', () => addLetyWebhook({ body: letyBodyPlaceholder }));
  if (letyAddConditionButton) letyAddConditionButton.addEventListener('click', () => addLetyCondition());
  if (freshworksAddMappingButton) freshworksAddMappingButton.addEventListener('click', () => addFreshworksMapping());
  if (integrationAddMappingButton) integrationAddMappingButton.addEventListener('click', () => addIntegrationMapping());
  bootKommoPipeline();
  bootAtom();
  bootLety();
  bootFreshworks();
  bootIntegrationMappings();
  refresh();
});
</script>
