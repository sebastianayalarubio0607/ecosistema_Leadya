@php
    $name = old('name', $customer?->name);
    $description = old('description', $customer?->description);
    $status = old('status', $customer?->status ?? 'active');
    $fb_pixel_id = old('fb_pixel_id', $customer?->fb_pixel_id);
    $fb_access_token = old('fb_access_token', $customer?->fb_access_token);
@endphp

<div class="bg-white dark:bg-gray-900 rounded shadow p-4 space-y-4">
    <div>
        <label class="block mb-1 dark:text-white">Nombre *</label>
        <input class="w-full rounded border p-2 dark:bg-gray-900  dark:text-white" name="name" value="{{ $name }}" />
               
        @error('name') <p class="text-red-600 text-sm mt-1 ">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block mb-1  dark:text-white">Descripción</label>
        <textarea name="description" class="w-full rounded border p-2 dark:bg-gray-900 dark:text-white" rows="4">{{ $description }}</textarea>
        @error('description') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        @php
    // Por defecto activo = 1
    $status = old('status', isset($customer) ? (int) $customer->status : 1);
@endphp

<div>
    <label class="block mb-1  dark:text-white ">Status *</label>

    <select name="status" class="w-full rounded border p-2 bg-white dark:bg-gray-900 text-gray-900 dark:text-white border-gray-300 dark:border-gray-700">
        <option value="1" @selected((string)$status === '1')>Activo</option>
        <option value="0" @selected((string)$status === '0')>Inactivo</option>
    </select>

    @error('status') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
</div>

    </div>

    <div>
        <label class="block mb-1  dark:text-white">FB Pixel ID</label>
        <input class="w-full rounded border p-2 dark:bg-gray-900  dark:text-white" name="fb_pixel_id" value="{{ $fb_pixel_id }}"
             
        @error('fb_pixel_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block mb-1  dark:text-white">FB Access Token</label>
        <input name="fb_access_token" value="{{ $fb_access_token }}"
               class="w-full rounded border p-2 dark:bg-gray-900 dark:text-white" />
        @error('fb_access_token') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>
</div>
