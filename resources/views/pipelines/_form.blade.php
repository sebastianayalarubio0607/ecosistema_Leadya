<div>
    <label for="integration_id" class="mb-1 block text-sm text-white/70">Integración</label>
    <select id="integration_id" name="integration_id" required class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white">
        <option value="">Selecciona una integración</option>
        @foreach($integrations as $integration)
            <option value="{{ $integration->id }}" @selected((string) old('integration_id', $pipeline->integration_id) === (string) $integration->id)>
                {{ $integration->customer?->name ?? 'Sin cliente' }} / {{ $integration->name }} (#{{ $integration->id }})
            </option>
        @endforeach
    </select>
    @error('integration_id')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
</div>
<div>
    <label for="external_id" class="mb-1 block text-sm text-white/70">ID externo</label>
    <input id="external_id" name="external_id" value="{{ old('external_id', $pipeline->external_id) }}" maxlength="255" required class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white">
    @error('external_id')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
</div>
<div>
    <label for="name" class="mb-1 block text-sm text-white/70">Nombre</label>
    <input id="name" name="name" value="{{ old('name', $pipeline->name) }}" maxlength="255" required class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white">
    @error('name')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
</div>
