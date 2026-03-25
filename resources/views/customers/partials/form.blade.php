@php
    $name = old('name', $customer?->name);
    $description = old('description', $customer?->description);
    $status = old('status', isset($customer) ? (int) $customer->status : 1);
    $fbPixelId = old('fb_pixel_id', $customer?->fb_pixel_id);
    $fbAccessToken = old('fb_access_token', $customer?->fb_access_token);
    $selectedMetaPageIds = old('meta_page_ids', $selectedMetaPageIds ?? []);
@endphp

<div class="bg-white dark:bg-gray-900 rounded shadow p-4 space-y-4">
    <div>
        <label class="block mb-1 dark:text-white">Nombre *</label>
        <input class="w-full rounded border p-2 dark:bg-gray-900 dark:text-white"
               name="name"
               value="{{ $name }}" />
        @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block mb-1 dark:text-white">Descripción</label>
        <textarea name="description" class="w-full rounded border p-2 dark:bg-gray-900 dark:text-white" rows="4">{{ $description }}</textarea>
        @error('description') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block mb-1 dark:text-white">Status *</label>
        <select name="status" class="w-full rounded border p-2 bg-white dark:bg-gray-900 text-gray-900 dark:text-white border-gray-300 dark:border-gray-700">
            <option value="1" @selected((string) $status === '1')>Activo</option>
            <option value="0" @selected((string) $status === '0')>Inactivo</option>
        </select>
        @error('status') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block mb-1 dark:text-white">FB Pixel ID</label>
        <input class="w-full rounded border p-2 dark:bg-gray-900 dark:text-white"
               name="fb_pixel_id"
               value="{{ $fbPixelId }}" />
        @error('fb_pixel_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block mb-1 dark:text-white">FB Access Token</label>
        <input name="fb_access_token"
               value="{{ $fbAccessToken }}"
               class="w-full rounded border p-2 dark:bg-gray-900 dark:text-white" />
        @error('fb_access_token') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block mb-2 dark:text-white">Meta Pages asociadas</label>
        <div class="rounded border p-3 space-y-2 dark:border-gray-700">
            @forelse(($metaPages ?? collect()) as $metaPage)
                <label class="flex items-start gap-2 dark:text-white">
                    <input type="checkbox"
                           name="meta_page_ids[]"
                           value="{{ $metaPage->id }}"
                           @checked(in_array($metaPage->id, $selectedMetaPageIds, true))>
                    <span>
                        {{ $metaPage->name }} ({{ $metaPage->meta_page_id }})
                        @if($metaPage->customer_id && $metaPage->customer_id !== ($customer->id ?? null))
                            <span class="text-xs text-amber-600">Actualmente asignada a otro customer</span>
                        @endif
                    </span>
                </label>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">No hay Meta Pages disponibles aún.</p>
            @endforelse
        </div>
        @error('meta_page_ids') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        @error('meta_page_ids.*') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>
</div>
