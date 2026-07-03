<div class="grid gap-4">
    <div>
        <label class="block mb-1 text-white/70">Nombre *</label>
        <input
            name="name"
            value="{{ old('name', $currency->name ?? '') }}"
            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
            placeholder="Ej: Peso Colombiano"
            required
        >
    </div>

    <div>
        <label class="block mb-1 text-white/70">Codigo *</label>
        <input
            name="code"
            value="{{ old('code', $currency->code ?? '') }}"
            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40 uppercase"
            placeholder="Ej: COP"
            maxlength="3"
            required
        >
    </div>

    <div>
        <label class="block mb-1 text-white/70">Estado *</label>
        @php($status = (string) old('status', isset($currency) ? (int) $currency->status : 1))
        <select
            name="status"
            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white"
            required
        >
            <option value="1" @selected($status === '1')>Activa</option>
            <option value="0" @selected($status === '0')>Inactiva</option>
        </select>
    </div>

    <div class="flex gap-2 pt-2">
        <button class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10" type="submit">
            Guardar
        </button>

        <a href="{{ route('currencies.index') }}"
           class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
            Cancelar
        </a>
    </div>
</div>
