<div class="grid gap-4">
    <div>
        <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">Nombre *</label>
        <input
            name="name"
            value="{{ old('name', $qualification->name ?? '') }}"
            class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200"
            placeholder="Ej: frío"
            required
        />
        @error('name')
            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
        @enderror
    </div>

    <div>
        <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">Funnel (opcional)</label>
        <select name="funnel_id" class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200">
            <option value="">-- Sin funnel --</option>
            @foreach(($funnels ?? []) as $f)
                <option value="{{ $f->id }}" @selected((string)old('funnel_id', $qualification->funnel_id ?? '') === (string)$f->id)>
                    {{ $f->name }} (ID: {{ $f->id }})
                </option>
            @endforeach
        </select>
        @error('funnel_id')
            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
        @enderror
    </div>

    <div class="flex gap-2 pt-2">
        <button class="px-4 py-2 rounded bg-green-600 text-white" type="submit">
            Guardar
        </button>

        <a href="{{ route('qualifications.index') }}"
           class="px-4 py-2 rounded bg-gray-200 dark:bg-gray-200">
            Cancelar
        </a>
    </div>
</div>
