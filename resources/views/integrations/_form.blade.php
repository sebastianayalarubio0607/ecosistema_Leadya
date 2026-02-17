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
        <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">Tipo de Integración *</label>
        <select name="integrationtype_id" class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200" required>
            <option value="">Seleccione...</option>
            @foreach($types as $t)
                <option value="{{ $t->id }}"
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

    <div>
        <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">Token (tokent)</label>
        <input name="tokent" value="{{ old('tokent', $integration->tokent ?? '') }}"
               class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200">
        @error('tokent') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
    </div>

    <div>
        <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">Status *</label>
        <select name="status" class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200" required>
            <option value="1" {{ (int) old('status', $integration->status ?? 1) === 1 ? 'selected' : '' }}>Activo</option>
            <option value="0" {{ (int) old('status', $integration->status ?? 1) === 0 ? 'selected' : '' }}>Inactivo</option>
        </select>
        @error('status') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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

    <div>
        <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">Descripción</label>
        <textarea name="description" rows="4"
                  class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200">{{ old('description', $integration->description ?? '') }}</textarea>
        @error('description') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
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
