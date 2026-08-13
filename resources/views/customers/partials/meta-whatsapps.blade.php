@php
    $metaWhatsapps = $customer?->metaWhatsapps ?? collect();
    $selectedMetaWhatsappIds = array_map('intval', $selectedMetaWhatsappIds ?? []);
    $newMetaWhatsapp = old('new_meta_whatsapp', []);
@endphp

<div>
    <div class="mb-2 flex items-center justify-between gap-3">
        <label class="block text-white/70">Meta WhatsApp asociadas</label>

        @if(isset($customer) && $customer)
            <a href="{{ route('meta.whatsapps.create', ['customer_id' => $customer->id]) }}"
               class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs text-white">
                + Nueva WhatsApp
            </a>
        @endif
    </div>

    <div class="rounded-2xl border border-white/10 bg-white/5 p-3 space-y-3">
        @if(isset($customer) && $customer)
            @forelse($metaWhatsapps as $whatsapp)
                <div class="rounded-xl border border-white/10 bg-slate-900/40 p-3">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div class="min-w-0">
                            <div class="font-semibold text-white break-all">{{ $whatsapp->waba_id }}</div>
                            <div class="text-sm text-white/60 break-all">Phone: {{ $whatsapp->phone_number_id ?: '-' }}</div>
                            <div class="text-sm text-white/60 break-all">WA ID: {{ $whatsapp->wa_id ?: '-' }}</div>
                            <div class="mt-1 text-xs text-white/50">
                                Token: {{ is_null($whatsapp->token_can_view_account) ? 'Sin validar' : ($whatsapp->token_can_view_account ? 'Si' : 'No') }}
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex rounded-lg border px-2 py-1 text-xs font-semibold {{ $whatsapp->is_subscribed_to_meta_app ? 'bg-emerald-500/15 border-emerald-300/30 text-emerald-200' : 'bg-rose-500/15 border-rose-300/30 text-rose-200' }}">
                                Meta app: {{ $whatsapp->is_subscribed_to_meta_app ? 'Suscrita' : 'No suscrita' }}
                            </span>

                            <span class="px-2 py-1 rounded-lg text-xs border {{ $whatsapp->status ? 'bg-emerald-500/10 border-emerald-300/20 text-emerald-200' : 'bg-white/10 border-white/10 text-white/70' }}">
                                {{ $whatsapp->status ? 'Activo' : 'Inactivo' }}
                            </span>

                            <a href="{{ route('meta.whatsapps.edit', $whatsapp) }}"
                               class="px-3 py-1.5 rounded-lg bg-indigo-500/20 hover:bg-indigo-500/30 border border-white/10 text-xs text-white">
                                Editar
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-white/50">Sin Meta WhatsApp asociadas.</p>
            @endforelse
        @endif

        <div>
            <label class="block mb-2 text-white/70">Asociar Meta WhatsApp existentes</label>
            <div class="rounded-xl border border-white/10 bg-slate-900/40 p-3 space-y-2">
                @forelse(($metaWhatsappsOptions ?? $metaWhatsapps ?? collect()) as $whatsapp)
                    <x-toggle-switch
                        name="meta_whatsapp_ids[]"
                        value="{{ $whatsapp->id }}"
                        :checked="in_array((int) $whatsapp->id, $selectedMetaWhatsappIds, true)"
                    >
                        <span class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-2">
                            <span class="break-all">{{ $whatsapp->waba_id }}</span>
                            <span class="text-xs text-white/50 break-all">Phone: {{ $whatsapp->phone_number_id ?: '-' }}</span>
                            <span class="inline-flex w-fit rounded-lg border px-2 py-0.5 text-[11px] font-semibold {{ $whatsapp->is_subscribed_to_meta_app ? 'bg-emerald-500/15 border-emerald-300/30 text-emerald-200' : 'bg-rose-500/15 border-rose-300/30 text-rose-200' }}">
                                {{ $whatsapp->is_subscribed_to_meta_app ? 'Suscrita' : 'No suscrita' }}
                            </span>
                        </span>
                    </x-toggle-switch>
                @empty
                    <p class="text-sm text-white/50">No hay Meta WhatsApp disponibles aun.</p>
                @endforelse
            </div>
            @error('meta_whatsapp_ids') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
            @error('meta_whatsapp_ids.*') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
        </div>

        <div class="rounded-xl border border-white/10 bg-slate-900/40 p-3 space-y-3">
            <div class="text-sm font-semibold text-white/80">Agregar Meta WhatsApp</div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div>
                    <label class="block mb-1 text-white/70">WABA ID</label>
                    <input name="new_meta_whatsapp[waba_id]"
                           value="{{ $newMetaWhatsapp['waba_id'] ?? '' }}"
                           class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40">
                    @error('new_meta_whatsapp.waba_id') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block mb-1 text-white/70">Phone Number ID</label>
                    <input name="new_meta_whatsapp[phone_number_id]"
                           value="{{ $newMetaWhatsapp['phone_number_id'] ?? '' }}"
                           class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40">
                    @error('new_meta_whatsapp.phone_number_id') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block mb-1 text-white/70">WA ID</label>
                    <input name="new_meta_whatsapp[wa_id]"
                           value="{{ $newMetaWhatsapp['wa_id'] ?? '' }}"
                           class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40">
                    @error('new_meta_whatsapp.wa_id') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block mb-1 text-white/70">Estado</label>
                    @php($newStatus = $newMetaWhatsapp['status'] ?? '1')
                    <select name="new_meta_whatsapp[status]"
                            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                        <option value="1" @selected((string) $newStatus === '1')>Activo</option>
                        <option value="0" @selected((string) $newStatus === '0')>Inactivo</option>
                    </select>
                    @error('new_meta_whatsapp.status') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
    </div>
</div>
