<div class="grid gap-4">
    <div>
        <label class="block mb-1 text-white/70">Código *</label>
        <input
            name="code"
            value="{{ old('code', $geo->code ?? '') }}"
            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
            placeholder="Ej: bog"
            maxlength="20"
            required
        >
    </div>

    <div>
        <label class="block mb-1 text-white/70">Nombre *</label>
        <input
            name="name"
            value="{{ old('name', $geo->name ?? '') }}"
            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
            placeholder="Ej: Bogota"
            required
        >
    </div>

    <div>
        <label class="block mb-1 text-white/70">Estado *</label>
        @php($isActive = (string) old('is_active', isset($geo) ? (int) $geo->is_active : 1))
        <select
            name="is_active"
            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white"
            required
        >
            <option value="1" @selected($isActive === '1')>Activo</option>
            <option value="0" @selected($isActive === '0')>Inactivo</option>
        </select>
    </div>

    <div class="flex gap-2 pt-2">
        <button class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10" type="submit">
            Guardar
        </button>

        <a href="{{ route('geos.index') }}"
           class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
            Cancelar
        </a>
    </div>
</div>
