@php
    /** @var \App\Models\MetaEvent|null $item */
    $item = $item ?? null;
@endphp

<div class="space-y-4">
    <div>
        <label class="block mb-1 text-white/70">Nombre</label>
        <input name="nombre" value="{{ old('nombre', $item?->nombre) }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
               placeholder="Ej: Lead, Purchase, CompleteRegistration">
        @error('nombre') <div class="text-rose-300 text-xs mt-1">{{ $message }}</div> @enderror
    </div>

    <div>
        <label class="block mb-1 text-white/70">Estado</label>
        @php($val = old('estados', $item?->estados ?? 'activo'))
        <select name="estados"
                class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
            <option value="activo" @selected($val === 'activo')>activo</option>
            <option value="inactivo" @selected($val === 'inactivo')>inactivo</option>
        </select>
        @error('estados') <div class="text-rose-300 text-xs mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="flex gap-2">
        <button class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">
            {{ $submitText ?? 'Guardar' }}
        </button>

        <a href="{{ $cancelUrl ?? route('meta.meta-events.index') }}"
           class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
            Cancelar
        </a>
    </div>
</div>
