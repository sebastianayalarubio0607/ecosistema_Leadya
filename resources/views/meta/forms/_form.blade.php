<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="md:col-span-2">
        <label class="block mb-1 text-white/70">Meta Page *</label>
        @php($selectedPage = old('meta_page_id', $form->meta_page_id ?? ''))
        <select name="meta_page_id" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" required>
            <option value="">-- Seleccionar --</option>
            @foreach($pages as $pageOption)
                <option value="{{ $pageOption->id }}" @selected((string) $selectedPage === (string) $pageOption->id)>
                    {{ $pageOption->name }} (ID: {{ $pageOption->id }})
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block mb-1 text-white/70">Meta Form ID *</label>
        <input name="meta_form_id" value="{{ old('meta_form_id', $form->meta_form_id ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40" required>
    </div>

    <div>
        <label class="block mb-1 text-white/70">Nombre *</label>
        <input name="name" value="{{ old('name', $form->name ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40" required>
    </div>

    <div>
        <label class="block mb-1 text-white/70">Locale</label>
        <input name="locale" value="{{ old('locale', $form->locale ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40">
    </div>

    <div>
        <label class="block mb-1 text-white/70">Meta Status</label>
        <input name="meta_status" value="{{ old('meta_status', $form->meta_status ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40">
    </div>

    <div class="md:col-span-2">
        <label class="block mb-1 text-white/70">Estado CRM</label>
        @php($status = (string) old('status', isset($form) ? (int) $form->status : 0))
        <select name="status" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
            <option value="1" @selected($status === '1')>Activo</option>
            <option value="0" @selected($status === '0')>Inactivo</option>
        </select>
    </div>
</div>
