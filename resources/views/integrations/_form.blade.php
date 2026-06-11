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

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 hidden" data-show-for="zoho">
        <div><label class="block mb-1 text-white/70">client_id</label><input name="client_id" value="{{ old('client_id', $integration->client_id ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">@error('client_id') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
        <div><label class="block mb-1 text-white/70">client_secret</label><input name="client_secret" value="{{ old('client_secret', $integration->client_secret ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">@error('client_secret') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
        <div><label class="block mb-1 text-white/70">code</label><input name="code" value="{{ old('code', $integration->code ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">@error('code') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
        <div><label class="block mb-1 text-white/70">access_token</label><input name="access_token" value="{{ old('access_token', $integration->tokent ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">@error('access_token') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
        <div><label class="block mb-1 text-white/70">refresh_token</label><input name="refresh_token" value="{{ old('refresh_token', $integration->refresh_token ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">@error('refresh_token') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 hidden" data-show-for="freshworks">
        <div><label class="block mb-1 text-white/70">token *</label><input name="tokent" value="{{ old('tokent', $integration->tokent ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" data-required-for="freshworks">@error('tokent') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
        <div><label class="block mb-1 text-white/70">territory_id *</label><input name="territory_id" value="{{ old('territory_id', $integration->territory_id ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" data-required-for="freshworks">@error('territory_id') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
        <div><label class="block mb-1 text-white/70">owner_id *</label><input name="owner_id" value="{{ old('owner_id', $integration->owner_id ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" data-required-for="freshworks">@error('owner_id') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
        <div><label class="block mb-1 text-white/70">City *</label><input name="city" value="{{ old('city', $integration->city ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" data-required-for="freshworks">@error('city') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
        <div><label class="block mb-1 text-white/70">lead_source_id *</label><input name="lead_source_id" value="{{ old('lead_source_id', $integration->lead_source_id ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" data-required-for="freshworks">@error('lead_source_id') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
        <div class="md:col-span-2"><label class="block mb-1 text-white/70">custom_field *</label><textarea name="custom_field" rows="8" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 font-mono text-sm text-white" placeholder='json con los campos necesarios para crear el lead' data-required-for="freshworks">{{ old('custom_field', $integration->custom_field ?? '') }}</textarea>@error('custom_field') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
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
  const leadFields = @json(array_values($leadFields ?? []));
  const initialKommoConditions = @json($kommoPipelineInitialConditions ?? []);
  const kommoPipelinesUrl = @json(($integration->exists ?? false) ? route('integrations.kommo-pipeline.pipelines', $integration) : null);
  const kommoStatusesUrlTemplate = @json(($integration->exists ?? false) ? route('integrations.kommo-pipeline.statuses', [$integration, '__PIPELINE_ID__']) : null);
  let kommoPipelinesCache = null;
  let kommoConditionIndex = 0;

  function normalizeTypeKey(raw) {
    const key = (raw || '').trim().toLowerCase();
    if (key.includes('google')) return 'google_sheets';
    if (key.includes('kommopipeline') || key.includes('kommo_pipeline') || key.includes('kommo pipeline')) return 'kommopipeline';
    if (key.includes('kommo')) return 'kommo';
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
      const shouldHideBaseUrl = key === 'hubspot';
      const shouldRequireBaseUrl = key !== 'hubspot' && key !== 'gohighlevel';
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
    input.value = value || '';
    input.placeholder = placeholder;
    input.className = 'w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white';
    return input;
  }

  function hiddenInput(name, value = '') {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value || '';
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
  bootKommoPipeline();
  refresh();
});
</script>
