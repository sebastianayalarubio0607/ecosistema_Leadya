<div class="grid gap-4">
    <div>
        <label class="block mb-1 text-white/70">Source *</label>
        @php($sourceId = (string) old('source_id', $siteLink->source_id ?? ''))
        <select
            name="source_id"
            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white"
            required
        >
            <option value="" @selected($sourceId === '')>Seleccionar source</option>
            @foreach($sources as $source)
                <option value="{{ $source->id }}" @selected($sourceId === (string) $source->id)>
                    {{ $source->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block mb-1 text-white/70">Codigo *</label>
        <input
            name="code"
            value="{{ old('code', $siteLink->code ?? '') }}"
            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
            placeholder="Ej: COT"
            maxlength="20"
            required
        >
    </div>

    <div>
        <label class="block mb-1 text-white/70">Valor del Site Link *</label>
        <input
            name="name"
            value="{{ old('name', $siteLink->name ?? '') }}"
            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
            placeholder="Ej: Cotizacion"
            required
        >
    </div>

    <div>
        <label class="block mb-1 text-white/70">Estado *</label>
        @php($isActive = (string) old('is_active', isset($siteLink) ? (int) $siteLink->is_active : 1))
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

        <a href="{{ route('site-links.index') }}"
           class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
            Cancelar
        </a>
    </div>
</div>
