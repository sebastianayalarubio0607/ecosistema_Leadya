<div class="grid gap-4">
    <div>
        <label class="block mb-1 text-white/70">Nombre *</label>
        <input name="name" value="{{ old('name', $type->name ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
               placeholder="Ej: Facebook Ads"
               required>
        @error('name')
            <div class="mt-1 text-sm text-rose-300">{{ $message }}</div>
        @enderror
    </div>

    <div>
        <label class="block mb-1 text-white/70">DescripciÃ³n</label>
        <textarea name="description" rows="4"
                  class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
                  placeholder="Opcional...">{{ old('description', $type->description ?? '') }}</textarea>
        @error('description')
            <div class="mt-1 text-sm text-rose-300">{{ $message }}</div>
        @enderror
    </div>

    <div>
        <label class="block mb-1 text-white/70">Status</label>
        <select name="status" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
            <option value="1" {{ (int) old('status', $type->status ?? 1) === 1 ? 'selected' : '' }}>Activo</option>
            <option value="0" {{ (int) old('status', $type->status ?? 1) === 0 ? 'selected' : '' }}>Inactivo</option>
        </select>
        @error('status')
            <div class="mt-1 text-sm text-rose-300">{{ $message }}</div>
        @enderror
    </div>

    <div class="flex gap-2 pt-2">
        <button class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10" type="submit">
            Guardar
        </button>

        <a href="{{ route('integrationtypes.index') }}"
           class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
            Cancelar
        </a>
    </div>
</div>
