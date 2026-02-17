@php
<<<<<<< HEAD
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
=======
    /** @var \App\Models\CrmState|null $crmstate */
    $crmstate = $crmstate ?? null;

    // create: se usa integration_id + external_id
    $isCreate = $isCreate ?? false;
@endphp

<div class="space-y-4">
    @if($isCreate)
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
            <div class="md:col-span-7">
                <label class="block mb-1 text-white/70">Integración</label>
                <select name="integration_id"
                        class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">— Selecciona —</option>
                    @foreach($integrations as $in)
                        <option value="{{ $in->id }}" @selected(old('integration_id') == $in->id)>
                            {{ $in->customer?->name ?? '—' }} — {{ $in->name }} ({{ $in->id }})
                        </option>
                    @endforeach
                </select>
                @error('integration_id') <div class="text-rose-300 text-xs mt-1">{{ $message }}</div> @enderror
            </div>

            <div class="md:col-span-5">
                <label class="block mb-1 text-white/70">External ID (del CRM)</label>
                <input name="external_id" value="{{ old('external_id') }}"
                       class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
                       placeholder="Ej: 123, WON, status_1">
                @error('external_id') <div class="text-rose-300 text-xs mt-1">{{ $message }}</div> @enderror
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
            <div class="md:col-span-6">
                <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                    <div class="text-xs text-white/50">Integration ID</div>
                    <div class="text-white">{{ $integrationId ?? '—' }}</div>
                    <div class="text-xs text-white/50 mt-2">Customer / Integration</div>
                    <div class="text-white">
                        {{ $integration?->customer?->name ?? '—' }} — {{ $integration?->name ?? '—' }}
                    </div>
                </div>
            </div>

            <div class="md:col-span-6">
                <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                    <div class="text-xs text-white/50">External ID</div>
                    <div class="text-white">{{ $externalId ?? '—' }}</div>
                    <div class="text-xs text-white/50 mt-2">CRM State ID</div>
                    <div class="text-white">{{ $crmstate?->id ?? '—' }}</div>
>>>>>>> 3ac2fef11dafeeab5dedfae1f504ba67206b2bba
                </div>
            </div>
        </div>
    @endif

    <div>
<<<<<<< HEAD
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
=======
        <label class="block mb-1 text-white/70">Nombre</label>
        <input name="name" value="{{ old('name', $crmstate?->name) }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
               placeholder="Ej: Nuevo, Contactado, Ganado, Perdido">
        @error('name') <div class="text-rose-300 text-xs mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
        <div class="md:col-span-6">
            <label class="block mb-1 text-white/70">Qualification</label>
            <select name="qualification"
                    class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                <option value="">— Selecciona —</option>
                @foreach($qualifications as $q)
                    <option value="{{ $q->id }}" @selected(old('qualification', $crmstate?->qualification) == $q->id)>
                        {{ $q->name }}
                    </option>
                @endforeach
            </select>
            @error('qualification') <div class="text-rose-300 text-xs mt-1">{{ $message }}</div> @enderror
        </div>

        <div class="md:col-span-6">
            <label class="block mb-1 text-white/70">Meta Event (Conversión)</label>
            <select name="meta_event_id"
                    class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                <option value="">— Sin asignar —</option>
                @foreach($metaEvents as $me)
                    <option value="{{ $me->id }}" @selected(old('meta_event_id', $crmstate?->meta_event_id) == $me->id)>
                        {{ $me->nombre }} ({{ $me->estados }})
                    </option>
                @endforeach
            </select>
            @error('meta_event_id') <div class="text-rose-300 text-xs mt-1">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="flex gap-2">
        <button class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">
            {{ $submitText ?? 'Guardar' }}
        </button>

        <a href="{{ $cancelUrl ?? route('crmstates.index') }}"
           class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
>>>>>>> 3ac2fef11dafeeab5dedfae1f504ba67206b2bba
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
