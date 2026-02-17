<div class="grid gap-4">
    <div>
        <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">Nombre *</label>
        <input name="name" value="{{ old('name', $type->name ?? '') }}"
               class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200"
               placeholder="Ej: Facebook Ads"
               required>
        @error('name')
            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
        @enderror
    </div>

    <div>
        <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">Descripción</label>
        <textarea name="description" rows="4"
                  class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200"
                  placeholder="Opcional...">{{ old('description', $type->description ?? '') }}</textarea>
        @error('description')
            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
        @enderror
    </div>

    <div>
        <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">Status</label>
        <select name="status" class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200">
            <option value="1" {{ (int) old('status', $type->status ?? 1) === 1 ? 'selected' : '' }}>Activo</option>
            <option value="0" {{ (int) old('status', $type->status ?? 1) === 0 ? 'selected' : '' }}>Inactivo</option>
        </select>
        @error('status')
            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
        @enderror
    </div>

    <div class="flex gap-2 pt-2">
        <button class="px-4 py-2 rounded bg-green-600 text-white" type="submit">
            Guardar
        </button>

        <a href="{{ route('integrationtypes.index') }}"
           class="px-4 py-2 rounded bg-gray-200 dark:bg-gray-200">
            Cancelar
        </a>
    </div>
</div>
