@php
    $name = old('name', $customer?->name);
    $description = old('description', $customer?->description);
    $status = old('status', isset($customer) ? (int) $customer->status : 1);
    $fbPixelId = old('fb_pixel_id', $customer?->fb_pixel_id);
    $fbAccessToken = old('fb_access_token', $customer?->fb_access_token);
    $fbTestEventCode = old('fb_test_event_code', $customer?->fb_test_event_code);
    $metaDataset = old('Meta_dataset', isset($customer) ? (int) $customer->Meta_dataset : 0);
    $metaDatasetId = old('Meta_dataset_id', $customer?->Meta_dataset_id);
    $metaDatasetToken = old('Meta_dataset_token', $customer?->Meta_dataset_token);
    $idGads = old('id_Gads', $customer?->id_Gads);
    $selectedCurrencyId = old('default_currency_id', $customer?->default_currency_id ?? ($defaultCurrencyId ?? null));
    $defaultLeadValue = old('default_lead_value', $customer?->default_lead_value ?? 100000);
    $selectedMetaPageIds = old('meta_page_ids', $selectedMetaPageIds ?? []);
    $selectedMetaWhatsappIds = old('meta_whatsapp_ids', $selectedMetaWhatsappIds ?? []);
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

    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
        <div class="mb-4">
            <h3 class="text-base font-semibold text-white">Conjuntos de datos</h3>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <section class="rounded-xl border border-white/10 bg-slate-900/40 p-4 space-y-4">
                <div>
                    <h4 class="text-sm font-semibold text-white">Conjuntos de datos pixel</h4>
                    <p class="mt-1 text-sm text-white/60">Se pondra el pixel de las landing page o Web.</p>
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
            </section>

            <section class="rounded-xl border border-white/10 bg-slate-900/40 p-4 space-y-4">
                <div>
                    <h4 class="text-sm font-semibold text-white">Conjuntos de datos formulario instantaneo</h4>
                    <p class="mt-1 text-sm text-white/60">Se pondra el conjunto de datos para medir por separado los formularios instantaneos de meta o de CRM.</p>
                </div>

                <div>
                    <input type="hidden" name="Meta_dataset" value="0">
                    <x-toggle-switch
                        name="Meta_dataset"
                        value="1"
                        label="Meta_dataset"
                        :checked="(string) $metaDataset === '1'"
                    >
                        Habilita el envio de conversiones de formularios instantaneos separados a los de landing page o web.
                    </x-toggle-switch>
                    @error('Meta_dataset') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block mb-1 text-white/70">Meta_dataset_id</label>
                    <input class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
                           name="Meta_dataset_id"
                           value="{{ $metaDatasetId }}" />
                    @error('Meta_dataset_id') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block mb-1 text-white/70">Meta_dataset_token</label>
                    <input name="Meta_dataset_token"
                           value="{{ $metaDatasetToken }}"
                           class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40" />
                    @error('Meta_dataset_token') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
                </div>
            </section>
        </div>
    </div>

    <div>
        <label class="block mb-1 text-white/70">FB Test Event Code</label>
        <input name="fb_test_event_code"
               value="{{ $fbTestEventCode }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
               placeholder="Ej: TEST6189" />
        @error('fb_test_event_code') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
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

    @include('customers.partials.meta-whatsapps', [
        'customer' => $customer,
        'metaWhatsappsOptions' => $metaWhatsapps ?? collect(),
        'selectedMetaWhatsappIds' => $selectedMetaWhatsappIds,
    ])

    <div>
        <label class="block mb-2 text-white/70">Meta Pages asociadas</label>
        <div class="rounded-2xl border border-white/10 bg-white/5 p-3 space-y-3">
            @forelse(($metaPages ?? collect()) as $metaPage)
                <x-toggle-switch
                    name="meta_page_ids[]"
                    value="{{ $metaPage->id }}"
                    :checked="in_array($metaPage->id, $selectedMetaPageIds, true)"
                >
                    <span class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-2">
                        <span class="break-all">
                            {{ $metaPage->name }} ({{ $metaPage->meta_page_id }})
                        </span>
                        <span class="inline-flex w-fit rounded-lg border px-2 py-0.5 text-[11px] font-semibold {{ $metaPage->is_leadgen_subscribed ? 'bg-emerald-500/15 border-emerald-300/30 text-emerald-200' : 'bg-rose-500/15 border-rose-300/30 text-rose-200' }}">
                            Leadgen: {{ $metaPage->is_leadgen_subscribed ? 'Suscrita' : 'No suscrita' }}
                        </span>
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
