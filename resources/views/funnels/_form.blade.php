<div class="grid gap-4">
    <div>
        <label class="block mb-1 text-white/70">Nombre *</label>
        <input
            name="name"
            value="{{ old('name', $funnel->name ?? '') }}"
            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
            placeholder="Ej: Funnel Meta Leads"
            required
        />
        @error('name')
            <div class="mt-1 text-sm text-rose-300">{{ $message }}</div>
        @enderror
    </div>

    <div>
        <label class="block mb-1 text-white/70">DescripciÃ³n</label>
        <textarea
            name="description"
            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
            rows="3"
            placeholder="(Opcional)">{{ old('description', $funnel->description ?? '') }}</textarea>
        @error('description')
            <div class="mt-1 text-sm text-rose-300">{{ $message }}</div>
        @enderror
    </div>

    <div>
        <label class="block mb-1 text-white/70">Estado *</label>
        <select name="status" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" required>
            @php $status = old('status', $funnel->status ?? 'active'); @endphp
            <option value="active" @selected($status === 'active')>active</option>
            <option value="inactive" @selected($status === 'inactive')>inactive</option>
        </select>
        @error('status')
            <div class="mt-1 text-sm text-rose-300">{{ $message }}</div>
        @enderror
    </div>

    <div>
        <label class="block mb-1 text-white/70">
            Qualifications (opcional)
        </label>

        <select name="qualification_ids[]" multiple
                class="min-h-[160px] w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white">
            @foreach($qualifications as $q)
                <option value="{{ $q->id }}" @selected(in_array($q->id, $selectedQualificationIds ?? []))>
                    {{ $q->name }} (ID: {{ $q->id }}){{ $q->funnel_id && $q->funnel_id != ($funnel->id ?? null) ? ' â€” asignada a otro funnel' : '' }}
                </option>
            @endforeach
        </select>

        <p class="mt-1 text-xs text-white/50">
            Selecciona varias (Ctrl/Cmd + clic). Si una qualification estÃ¡ asignada a otro funnel, al guardar se moverÃ¡ a este.
        </p>

        @error('qualification_ids')
            <div class="mt-1 text-sm text-rose-300">{{ $message }}</div>
        @enderror
        @error('qualification_ids.*')
            <div class="mt-1 text-sm text-rose-300">{{ $message }}</div>
        @enderror
    </div>

    <div class="flex gap-2 pt-2">
        <button class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10" type="submit">
            Guardar
        </button>

        <a href="{{ route('funnels.index') }}"
           class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
            Cancelar
        </a>
    </div>
</div>
