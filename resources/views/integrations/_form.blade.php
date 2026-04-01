<div class="grid gap-4">
    <div>
        <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">Nombre *</label>
        <input name="name" value="{{ old('name', $integration->name ?? '') }}"
               class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200"
               required>
        @error('name') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
    </div>

    <div>
        <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">Cliente *</label>
        <select name="customer_id" class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200" required>
            <option value="">Seleccione...</option>
            @foreach($customers as $c)
                <option value="{{ $c->id }}"
                    {{ (int) old('customer_id', $integration->customer_id ?? 0) === (int) $c->id ? 'selected' : '' }}>
                    {{ $c->name }}
                </option>
            @endforeach
        </select>
        @error('customer_id') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
    </div>

    <div>
        <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">Tipo de Integracion *</label>
        <select id="integrationtype_id" name="integrationtype_id"
                class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200" required>
            <option value="">Seleccione...</option>
            @foreach($types as $t)
                <option value="{{ $t->id }}"
                    data-key="{{ $t->key ?? \Illuminate\Support\Str::slug($t->name, '_') }}"
                    {{ (int) old('integrationtype_id', $integration->integrationtype_id ?? 0) === (int) $t->id ? 'selected' : '' }}>
                    {{ $t->name }}
                </option>
            @endforeach
        </select>
        @error('integrationtype_id') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
    </div>

    <div>
        <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">URL *</label>
        <input name="url" value="{{ old('url', $integration->url ?? '') }}"
               class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200"
               placeholder="https://..."
               required>
        @error('url') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
    </div>

    <input type="hidden" name="status" value="{{ old('status', $integration->status ?? 1) }}">

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 hidden" data-show-for="kommo">
        <div>
            <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">crm_Id_phone</label>
            <input name="crm_Id_phone" value="{{ old('crm_Id_phone', $integration->crm_Id_phone ?? '') }}"
                   class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200">
            @error('crm_Id_phone') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">crm_Id_email</label>
            <input name="crm_Id_email" value="{{ old('crm_Id_email', $integration->crm_Id_email ?? '') }}"
                   class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200">
            @error('crm_Id_email') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">crm_Id_service</label>
            <input name="crm_Id_service" value="{{ old('crm_Id_service', $integration->crm_Id_service ?? '') }}"
                   class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200">
            @error('crm_Id_service') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">crm_Id_fuente</label>
            <input name="crm_Id_fuente" value="{{ old('crm_Id_fuente', $integration->crm_Id_fuente ?? '') }}"
                   class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200">
            @error('crm_Id_fuente') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 hidden" data-show-for="zoho">
        <div>
            <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">client_id</label>
            <input name="client_id" value="{{ old('client_id', $integration->client_id ?? '') }}"
                   class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200">
            @error('client_id') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">client_secret</label>
            <input name="client_secret" value="{{ old('client_secret', $integration->client_secret ?? '') }}"
                   class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200">
            @error('client_secret') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">code</label>
            <input name="code" value="{{ old('code', $integration->code ?? '') }}"
                   class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200">
            @error('code') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">access_token</label>
            <input name="access_token" value="{{ old('access_token', $integration->tokent ?? '') }}"
                   class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200">
            @error('access_token') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">refresh_token</label>
            <input name="refresh_token" value="{{ old('refresh_token', $integration->refresh_token ?? '') }}"
                   class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200">
            @error('refresh_token') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 hidden" data-show-for="freshworks">
        <div>
            <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">token *</label>
            <input name="tokent" value="{{ old('tokent', $integration->tokent ?? '') }}"
                   class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200"
                   data-required-for="freshworks">
            @error('tokent') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">territory_id *</label>
            <input name="territory_id" value="{{ old('territory_id', $integration->territory_id ?? '') }}"
                   class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200"
                   data-required-for="freshworks">
            @error('territory_id') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">owner_id *</label>
            <input name="owner_id" value="{{ old('owner_id', $integration->owner_id ?? '') }}"
                   class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200"
                   data-required-for="freshworks">
            @error('owner_id') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">City *</label>
            <input name="city" value="{{ old('city', $integration->city ?? '') }}"
                   class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200"
                   data-required-for="freshworks">
            @error('city') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">lead_source_id *</label>
            <input name="lead_source_id" value="{{ old('lead_source_id', $integration->lead_source_id ?? '') }}"
                   class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200"
                   data-required-for="freshworks">
            @error('lead_source_id') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">custom_field *</label>
            <textarea name="custom_field" rows="8"
                      class="w-full rounded border p-2 font-mono text-sm dark:bg-gray-900 dark:text-gray-200"
                      placeholder='json con los campos necesarios para crear el lead'
                      data-required-for="freshworks">{{ old('custom_field', $integration->custom_field ?? '') }}</textarea>
            @error('custom_field') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 hidden" data-show-for="salesforce">
        <div>
            <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">url_credenciales *</label>
            <input name="url_credenciales" value="{{ old('url_credenciales', $integration->url_credenciales ?? '') }}"
                   class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200"
                   placeholder="https://..."
                   data-required-for="salesforce">
            @error('url_credenciales') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">Username *</label>
            <input name="username" value="{{ old('username', $integration->username ?? '') }}"
                   class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200"
                   data-required-for="salesforce">
            @error('username') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">Password *</label>
            <input name="password" type="password" value="{{ old('password', $integration->password ?? '') }}"
                   class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200"
                   data-required-for="salesforce">
            @error('password') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">token</label>
            <input name="tokent" value="{{ old('tokent', $integration->tokent ?? '') }}"
                   class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200"
                   readonly>
            @error('tokent') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">body *</label>
            <textarea name="body" rows="10"
                      class="w-full rounded border p-2 font-mono text-sm dark:bg-gray-900 dark:text-gray-200"
                      placeholder='json con el payload a enviar a Salesforce'
                      data-required-for="salesforce">{{ old('body', $integration->body ?? '') }}</textarea>
            @error('body') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 hidden" data-show-for="monday">
        <div>
            <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">Authorization *</label>
            <input name="tokent" value="{{ old('tokent', $integration->tokent ?? '') }}"
                   class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200"
                   placeholder="Token permanente de Monday"
                   data-required-for="monday">
            @error('tokent') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Se usara como header Authorization para las consultas GraphQL a Monday.</p>
        </div>
    </div>

    @if(isset($integration) && $integration->exists)
        <div class="bg-gray-50 dark:bg-gray-800 rounded p-3">
            <div class="text-sm text-gray-700 dark:text-gray-200">
                <div class="font-semibold mb-1">Public Key</div>
                <div class="font-mono text-xs break-all">{{ $integration->public_key }}</div>
            </div>

            <label class="mt-3 inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                <input type="checkbox" name="regenerate_public_key" value="1">
                Regenerar public_key al guardar
            </label>
        </div>
    @endif

    <div class="flex gap-2 pt-2">
        <button class="px-4 py-2 rounded bg-green-600 text-white" type="submit">
            Guardar
        </button>

        <a href="{{ route('integrations.index') }}"
           class="px-4 py-2 rounded bg-gray-200 dark:bg-gray-200">
            Cancelar
        </a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const typeSelect = document.getElementById('integrationtype_id');
  const conditionalBlocks = document.querySelectorAll('[data-show-for]');

  function normalizeTypeKey(raw) {
    const key = (raw || '').trim().toLowerCase();
    if (key.includes('google')) return 'google_sheets';
    if (key.includes('kommo')) return 'kommo';
    if (key.includes('zoho')) return 'zoho';
    if (key.includes('freshworks')) return 'freshworks';
    if (key.includes('salesforce')) return 'salesforce';
    if (key.includes('monday')) return 'monday';
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

  function refresh() {
    const key = getSelectedKey();

    conditionalBlocks.forEach(block => {
      const showFor = (block.dataset.showFor || '')
        .split(/\s+/)
        .map(s => s.trim().toLowerCase())
        .filter(Boolean);

      const hasError = !!block.querySelector('.text-red-600');
      const shouldShow = hasError || (key && showFor.includes(key));

      setBlockVisible(block, shouldShow, key);
    });
  }

  if (typeSelect) {
    typeSelect.addEventListener('change', refresh);
  }

  refresh();
});
</script>
