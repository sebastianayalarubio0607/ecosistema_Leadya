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

    <div class="grid grid-cols-1 gap-4 hidden" data-show-for="kommo freshworks hubspot">
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

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 hidden" data-show-for="salesforce">
        <div><label class="block mb-1 text-white/70">url_credenciales *</label><input name="url_credenciales" value="{{ old('url_credenciales', $integration->url_credenciales ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" placeholder="https://..." data-required-for="salesforce">@error('url_credenciales') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
        <div><label class="block mb-1 text-white/70">Username *</label><input name="username" value="{{ old('username', $integration->username ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" data-required-for="salesforce">@error('username') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
        <div><label class="block mb-1 text-white/70">Password *</label><input name="password" type="password" value="{{ old('password', $integration->password ?? '') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" data-required-for="salesforce">@error('password') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror</div>
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

  function normalizeTypeKey(raw) {
    const key = (raw || '').trim().toLowerCase();
    if (key.includes('google')) return 'google_sheets';
    if (key.includes('kommo')) return 'kommo';
    if (key.includes('zoho')) return 'zoho';
    if (key.includes('freshworks')) return 'freshworks';
    if (key.includes('salesforce')) return 'salesforce';
    if (key.includes('monday')) return 'monday';
    if (key.includes('hubspot')) return 'hubspot';
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
    const supportsCustomPrefix = key === 'kommo' || key === 'freshworks' || key === 'hubspot';
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
      baseUrlBlock.classList.toggle('hidden', shouldHideBaseUrl);
      baseUrlInput.disabled = shouldHideBaseUrl;
      baseUrlInput.required = !shouldHideBaseUrl;
    }

    refreshCrmPrefixRequirement();
  }

  if (typeSelect) typeSelect.addEventListener('change', refresh);
  if (crmPrefixToggle) crmPrefixToggle.addEventListener('change', refreshCrmPrefixRequirement);
  refresh();
});
</script>
