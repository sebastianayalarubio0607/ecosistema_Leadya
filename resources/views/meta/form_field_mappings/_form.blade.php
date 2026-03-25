<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="md:col-span-2">
        <label class="block mb-1 text-white/70">Formulario *</label>
        @php($selectedForm = old('meta_form_id', $mapping->meta_form_id ?? ''))
        <select name="meta_form_id" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" required>
            <option value="">-- Seleccionar --</option>
            @foreach($forms as $formOption)
                <option value="{{ $formOption->id }}" @selected((string) $selectedForm === (string) $formOption->id)>
                    {{ $formOption->name }} ({{ $formOption->meta_form_id }})
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block mb-1 text-white/70">Campo Meta</label>
        <input name="meta_field_name" value="{{ old('meta_field_name', $mapping->meta_field_name ?? '') }}"
               list="meta-field-options"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
               placeholder="Ej: email o déjalo vacío si usarás un valor estático">
        <datalist id="meta-field-options">
            @foreach($availableMetaFields as $field)
                <option value="{{ $field['name'] }}">{{ $field['label'] }}</option>
            @endforeach
        </datalist>
        <p class="mt-1 text-xs text-white/50">Opcional si quieres usar un valor fijo.</p>
    </div>

    <div>
        <label class="block mb-1 text-white/70">Campo Lead *</label>
        @php($selectedLeadField = old('lead_field_name', $mapping->lead_field_name ?? ''))
        <select name="lead_field_name" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" required>
            <option value="">-- Seleccionar --</option>
            @foreach($leadFields as $leadField)
                <option value="{{ $leadField }}" @selected($selectedLeadField === $leadField)>{{ $leadField }}</option>
            @endforeach
        </select>
    </div>

    <div class="md:col-span-2">
        <label class="block mb-1 text-white/70">Valor estático</label>
        <input name="static_value" value="{{ old('static_value', $mapping->static_value ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
               placeholder="Opcional, se usará cuando no quieras tomar un valor desde Meta">
        <p class="mt-1 text-xs text-white/50">Debes indicar un campo Meta o un valor estático.</p>
    </div>

    <div>
        <label class="block mb-1 text-white/70">Es requerido</label>
        @php($isRequired = (string) old('is_required', isset($mapping) ? (int) $mapping->is_required : 0))
        <select name="is_required" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
            <option value="1" @selected($isRequired === '1')>Sí</option>
            <option value="0" @selected($isRequired === '0')>No</option>
        </select>
    </div>

    <div>
        <label class="block mb-1 text-white/70">Activo</label>
        @php($isActive = (string) old('is_active', isset($mapping) ? (int) $mapping->is_active : 1))
        <select name="is_active" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
            <option value="1" @selected($isActive === '1')>Sí</option>
            <option value="0" @selected($isActive === '0')>No</option>
        </select>
    </div>
</div>
