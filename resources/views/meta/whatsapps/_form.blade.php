@php($selectedCustomerIds = array_map('intval', old('customer_ids', $selectedCustomerIds ?? $whatsapp->customers?->pluck('id')->all() ?? [])))

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="md:col-span-2">
        <label class="block mb-2 text-white/70">Customers</label>
        <div class="rounded-2xl border border-white/10 bg-white/5 p-3 space-y-2">
            @forelse($customers as $customer)
                <x-toggle-switch
                    name="customer_ids[]"
                    value="{{ $customer->id }}"
                    :checked="in_array((int) $customer->id, $selectedCustomerIds, true)"
                >
                    {{ $customer->name }} (ID: {{ $customer->id }})
                </x-toggle-switch>
            @empty
                <p class="text-sm text-white/50">No hay customers disponibles.</p>
            @endforelse
        </div>
        @error('customer_ids') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
        @error('customer_ids.*') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block mb-1 text-white/70">WABA ID *</label>
        <input name="waba_id"
               value="{{ old('waba_id', $whatsapp->waba_id ?? '') }}"
               required
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40">
        @error('waba_id') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block mb-1 text-white/70">Phone Number ID</label>
        <input name="phone_number_id"
               value="{{ old('phone_number_id', $whatsapp->phone_number_id ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40">
        @error('phone_number_id') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block mb-1 text-white/70">WA ID</label>
        <input name="wa_id"
               value="{{ old('wa_id', $whatsapp->wa_id ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40">
        @error('wa_id') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block mb-1 text-white/70">Estado</label>
        @php($statusValue = old('status', isset($whatsapp) ? (int) $whatsapp->status : 1))
        <select name="status" required class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
            <option value="1" @selected((string) $statusValue === '1')>Activo</option>
            <option value="0" @selected((string) $statusValue === '0')>Inactivo</option>
        </select>
        @error('status') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block mb-1 text-white/70">Suscripcion Meta</label>
        <input value="{{ $whatsapp->is_subscribed_to_meta_app ? 'Suscrita' : 'No suscrita' }}"
               disabled
               class="w-full rounded-xl border p-2 font-semibold {{ $whatsapp->is_subscribed_to_meta_app ? 'bg-emerald-500/15 border-emerald-300/30 text-emerald-200' : 'bg-rose-500/15 border-rose-300/30 text-rose-200' }}">
    </div>

    <div>
        <label class="block mb-1 text-white/70">Token puede consultar WABA</label>
        <input value="{{ is_null($whatsapp->token_can_view_account) ? 'Sin validar' : ($whatsapp->token_can_view_account ? 'Si' : 'No') }}"
               disabled
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/40 text-white/70">
    </div>

    <div class="md:col-span-2">
        <label class="block mb-1 text-white/70">subscribed_apps</label>
        <textarea rows="4" disabled
                  class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/40 text-white/70">{{ $whatsapp->subscribed_apps ? json_encode($whatsapp->subscribed_apps, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'Sin respuesta registrada' }}</textarea>
    </div>
</div>
