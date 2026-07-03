@php
    $name = old('name', $customer?->name);
    $description = old('description', $customer?->description);
    $status = old('status', isset($customer) ? (int) $customer->status : 1);
    $fbPixelId = old('fb_pixel_id', $customer?->fb_pixel_id);
    $fbAccessToken = old('fb_access_token', $customer?->fb_access_token);
    $idGads = old('id_Gads', $customer?->id_Gads);
    $selectedCurrencyId = old('default_currency_id', $customer?->default_currency_id ?? ($defaultCurrencyId ?? null));
    $defaultLeadValue = old('default_lead_value', $customer?->default_lead_value ?? 100000);
    $selectedMetaPageIds = old('meta_page_ids', $selectedMetaPageIds ?? []);
@endphp

<div class="space-y-4 text-white/80">
    <div>
        <label class="block mb-1 text-white/70">Nombre *</label>
        <input class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
               name="name"
               value="{{ $name }}" />
        @error('name') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block mb-1 text-white/70">Descripción</label>
        <textarea name="description" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40" rows="4">{{ $description }}</textarea>
        @error('description') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block mb-1 text-white/70">Status *</label>
        <select name="status" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
            <option value="1" @selected((string) $status === '1')>Activo</option>
            <option value="0" @selected((string) $status === '0')>Inactivo</option>
        </select>
        @error('status') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block mb-1 text-white/70">FB Pixel ID</label>
        <input class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
               name="fb_pixel_id"
               value="{{ $fbPixelId }}" />
        @error('fb_pixel_id') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block mb-1 text-white/70">FB Access Token</label>
        <input name="fb_access_token"
               value="{{ $fbAccessToken }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40" />
        @error('fb_access_token') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block mb-1 text-white/70">ID Google Ads</label>
        <input name="id_Gads"
               value="{{ $idGads }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
               placeholder="ID de la cuenta publicitaria de Google Ads. Solo números. Ej: 1234567890" />
        @error('id_Gads') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block mb-1 text-white/70">Divisa predeterminada</label>
            <select name="default_currency_id" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                @foreach(($currencies ?? collect()) as $currency)
                    <option value="{{ $currency->id }}" @selected((string) $selectedCurrencyId === (string) $currency->id)>
                        {{ $currency->code }} - {{ $currency->name }}{{ $currency->status ? '' : ' (inactiva)' }}
                    </option>
                @endforeach
            </select>
            @error('default_currency_id') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block mb-1 text-white/70">Valor minimo predeterminado</label>
            <input name="default_lead_value"
                   type="number"
                   min="0"
                   step="0.01"
                   value="{{ $defaultLeadValue }}"
                   class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
                   placeholder="100000" />
            @error('default_lead_value') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
        </div>
    </div>

    @include('customers.partials.meta-ad-accounts', ['customer' => $customer])

    <div>
        <label class="block mb-2 text-white/70">Meta Pages asociadas</label>
        <div class="rounded-2xl border border-white/10 bg-white/5 p-3 space-y-3">
            @forelse(($metaPages ?? collect()) as $metaPage)
                <x-toggle-switch
                    name="meta_page_ids[]"
                    value="{{ $metaPage->id }}"
                    :checked="in_array($metaPage->id, $selectedMetaPageIds, true)"
                >
                    <span class="block">
                        {{ $metaPage->name }} ({{ $metaPage->meta_page_id }})
                        @if($metaPage->customer_id && $metaPage->customer_id !== ($customer->id ?? null))
                            <span class="block text-xs text-amber-300">Actualmente asignada a otro customer</span>
                        @endif
                    </span>
                </x-toggle-switch>
            @empty
                <p class="text-sm text-white/50">No hay Meta Pages disponibles aÃºn.</p>
            @endforelse
        </div>
        @error('meta_page_ids') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
        @error('meta_page_ids.*') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
    </div>

    @if(!isset($customer) || !$customer)
        <div class="flex gap-2 pt-2">
            <button class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">Guardar</button>
            <a href="{{ route('customers.index') }}" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
                Cancelar
            </a>
        </div>
    @endif
</div>
