<div class="grid gap-4">
    <div>
        <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">Nombre *</label>
        <input
            name="name"
            value="{{ old('name', $funnel->name ?? '') }}"
            class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200"
            placeholder="Ej: Funnel Meta Leads"
            required
        />
        @error('name')
            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
        @enderror
    </div>

    <div>
        <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">Descripción</label>
        <textarea
            name="description"
            class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200"
            rows="3"
            placeholder="(Opcional)">{{ old('description', $funnel->description ?? '') }}</textarea>
        @error('description')
            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
        @enderror
    </div>

    <div>
        <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">Estado *</label>
        <select name="status" class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200" required>
            @php $status = old('status', $funnel->status ?? 'active'); @endphp
            <option value="active" @selected($status === 'active')>active</option>
            <option value="inactive" @selected($status === 'inactive')>inactive</option>
        </select>
        @error('status')
            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
        @enderror
    </div>

    {{-- ✅ MetaEvent --}}
    <div>
        <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">
            Meta Event (opcional)
        </label>

        @php $metaEventId = old('meta_event_id', $funnel->meta_event_id ?? ''); @endphp

        <select name="meta_event_id" class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200">
            <option value="">— Sin Meta Event —</option>

            @foreach($metaEvents as $ev)
                @php
                    // ✅ Aquí se obtiene el "nombre" del meta event
                    // Ajusta el orden si tu columna real tiene otro nombre.
                    $label = $ev->name
                        ?? $ev->event_name
                        ?? $ev->title
                        ?? $ev->event
                        ?? $ev->nombre
                        ?? ('MetaEvent #' . $ev->id);
                @endphp

                <option value="{{ $ev->id }}" @selected((string)$metaEventId === (string)$ev->id)>
                    {{ $label }}
                </option>
            @endforeach
        </select>

        @error('meta_event_id')
            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
        @enderror
    </div>

    {{-- ✅ Qualifications --}}
    <div>
        <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">
            Qualifications (opcional)
        </label>

        <select name="qualification_ids[]" multiple
                class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200 min-h-[160px]">
            @foreach($qualifications as $q)
                <option value="{{ $q->id }}" @selected(in_array($q->id, $selectedQualificationIds ?? []))>
                    {{ $q->name }} (ID: {{ $q->id }}){{ $q->funnel_id && $q->funnel_id != ($funnel->id ?? null) ? ' — asignada a otro funnel' : '' }}
                </option>
            @endforeach
        </select>

        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Selecciona varias (Ctrl/Cmd + clic). Si una qualification está asignada a otro funnel, al guardar se moverá a este.
        </p>

        @error('qualification_ids')
            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
        @enderror
        @error('qualification_ids.*')
            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
        @enderror
    </div>

    <div class="flex gap-2 pt-2">
        <button class="px-4 py-2 rounded bg-green-600 text-white" type="submit">
            Guardar
        </button>

        <a href="{{ route('funnels.index') }}"
           class="px-4 py-2 rounded bg-gray-200 dark:bg-gray-200">
            Cancelar
        </a>
    </div>
</div>
