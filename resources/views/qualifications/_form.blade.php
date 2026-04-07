<div class="grid gap-4">
    <div>
        <label class="block mb-1 text-white/70">Nombre *</label>
        <input
            name="name"
            value="{{ old('name', $qualification->name ?? '') }}"
            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
            placeholder="Ej: frÃ­o"
            required
        />
        @error('name')
            <div class="mt-1 text-sm text-rose-300">{{ $message }}</div>
        @enderror
    </div>

    <div>
        <label class="block mb-1 text-white/70">Funnel (opcional)</label>
        <select name="funnel_id" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
            <option value="">-- Sin funnel --</option>
            @foreach(($funnels ?? []) as $f)
                <option value="{{ $f->id }}" @selected((string)old('funnel_id', $qualification->funnel_id ?? '') === (string)$f->id)>
                    {{ $f->name }} (ID: {{ $f->id }})
                </option>
            @endforeach
        </select>
        @error('funnel_id')
            <div class="mt-1 text-sm text-rose-300">{{ $message }}</div>
        @enderror
    </div>

    <div class="flex gap-2 pt-2">
        <button class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10" type="submit">
            Guardar
        </button>

        <a href="{{ route('qualifications.index') }}"
           class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
            Cancelar
        </a>
    </div>
</div>
