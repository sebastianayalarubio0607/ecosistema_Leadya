@php
    $selectedCustomerIds = array_map('intval', old('customer_ids', $selectedCustomerIds ?? $ad_account->customers?->pluck('id')->all() ?? []));
    $selectedDefaultWhatsappCustomerId = old('default_whatsapp_lead_customer_id', $selectedDefaultWhatsappCustomerId ?? $ad_account->customers?->first(fn ($customer) => (bool) $customer->pivot->is_default_for_whatsapp_leads)?->id ?? null);
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block mb-1 text-white/70">Meta Account ID (account_id)</label>
        <input name="meta_account_id"
               value="{{ old('meta_account_id', $ad_account->meta_account_id ?? '') }}"
               required
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40">
    </div>

    <div>
        <label class="block mb-1 text-white/70">Nombre (account_name)</label>
        <input name="name"
               value="{{ old('name', $ad_account->name ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40">
    </div>

    <div class="md:col-span-2">
        <label class="block mb-1 text-white/70">Estado</label>
        @php($val = old('status', $ad_account->status ?? 'active'))
        <select name="status" required
                class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
            <option value="active" @selected($val==='active')>active</option>
            <option value="inactive" @selected($val==='inactive')>inactive</option>
        </select>
    </div>

    <div>
        <label class="block mb-1 text-white/70">Suscripcion Meta</label>
        <input value="{{ $ad_account->is_subscribed_to_meta_app ? 'Suscrita' : 'No suscrita' }}"
               disabled
               class="w-full rounded-xl border p-2 font-semibold {{ $ad_account->is_subscribed_to_meta_app ? 'bg-emerald-500/15 border-emerald-300/30 text-emerald-200' : 'bg-rose-500/15 border-rose-300/30 text-rose-200' }}">
    </div>

    <div>
        <label class="block mb-1 text-white/70">Token puede consultar</label>
        <input value="{{ is_null($ad_account->token_can_view_account) ? 'Sin validar' : ($ad_account->token_can_view_account ? 'Si' : 'No') }}"
               disabled
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/40 text-white/70">
    </div>

    <div class="md:col-span-2">
        <label class="block mb-1 text-white/70">subscribed_apps</label>
        <textarea rows="4" disabled
                  class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/40 text-white/70">{{ $ad_account->subscribed_apps ? json_encode($ad_account->subscribed_apps, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'Sin respuesta registrada' }}</textarea>
    </div>

    <div class="md:col-span-2">
        <label class="block mb-2 text-white/70">Clientes relacionados</label>
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

    <div class="md:col-span-2">
        <label class="block mb-1 text-white/70">Customer default para leads de WhatsApp</label>
        <select name="default_whatsapp_lead_customer_id"
                class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
            <option value="">Se asigna automaticamente si solo hay un customer</option>
            @foreach($customers as $customer)
                <option value="{{ $customer->id }}" @selected((string) $selectedDefaultWhatsappCustomerId === (string) $customer->id)>
                    {{ $customer->name }} (ID: {{ $customer->id }})
                </option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-white/50">
            Si la cuenta tiene varios clientes, este campo es obligatorio. Si queda inconsistente por datos viejos, el sistema usa como respaldo el cliente mas antiguo asociado a la cuenta.
        </p>
        @error('default_whatsapp_lead_customer_id') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
    </div>
</div>
