@php
  $isEdit = isset($crmstate) && $crmstate->exists;
@endphp

<div class="grid gap-4">
    @if(!$isEdit)
        <div>
            <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">Integración *</label>
            <select id="integration_id" name="integration_id"
                    class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200" required>
                <option value="">Seleccione...</option>
                @foreach($integrations as $i)
                    <option value="{{ $i->id }}" {{ old('integration_id') == $i->id ? 'selected' : '' }}>
                        {{ $i->id }} - {{ $i->customer->name ?? '—' }} - {{ $i->name }}
                    </option>
                @endforeach
            </select>
            @error('integration_id') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">ID del CRM (status_id) *</label>
            <input id="external_id" name="external_id" value="{{ old('external_id') }}"
                   class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200"
                   placeholder="Ej: 12345" required>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                El ID final se guardará como: <span class="font-mono" id="final_id_preview">integrationId-statusId</span>
            </p>
            @error('external_id') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
            @error('id') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>
    @else
        <div class="bg-gray-50 dark:bg-gray-800 rounded p-3">
            <div class="text-sm text-gray-800 dark:text-gray-200">
                <div class="font-semibold">ID (fijo)</div>
                <div class="font-mono text-xs break-all">{{ $crmstate->id }}</div>
                <div class="mt-2 text-xs text-gray-600 dark:text-gray-300">
                    Integración: <span class="font-mono">{{ $integrationId ?? '' }}</span> |
                    CRM status_id: <span class="font-mono">{{ $externalId ?? '' }}</span>
                </div>
            </div>
        </div>
    @endif

    <div>
        <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">Nombre *</label>
        <input name="name" value="{{ old('name', $crmstate->name ?? '') }}"
               class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200"
               placeholder="Ej: frío" required>
        @error('name') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
    </div>

    <div>
        <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">Qualification *</label>
        <select name="qualification"
                class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200" required>
            <option value="">Seleccione...</option>
            @foreach($qualifications as $q)
                <option value="{{ $q->id }}"
                    {{ (int) old('qualification', $crmstate->qualification ?? 0) === (int) $q->id ? 'selected' : '' }}>
                    {{ $q->name }}
                </option>
            @endforeach
        </select>
        @error('qualification') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
    </div>

    <div class="flex gap-2 pt-2">
        <button class="px-4 py-2 rounded bg-green-600 text-white" type="submit">
            Guardar
        </button>

        <a href="{{ route('crmstates.index') }}"
           class="px-4 py-2 rounded bg-gray-200 dark:bg-gray-200">
            Cancelar
        </a>
    </div>
</div>
<<<<<<< HEAD

@if(!$isEdit)
<script>
  (function () {
    const integration = document.getElementById('integration_id');
    const external = document.getElementById('external_id');
    const preview = document.getElementById('final_id_preview');

    function update() {
      const i = integration?.value || 'integrationId';
      const e = external?.value || 'statusId';
      preview.textContent = `${i}-${e}`;
    }
    integration?.addEventListener('change', update);
    external?.addEventListener('input', update);
    update();
  })();
</script>
@endif
=======
>>>>>>> 3ac2fef11dafeeab5dedfae1f504ba67206b2bba
