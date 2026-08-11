<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="md:col-span-2">
        <label class="block mb-1 text-white/70">Cliente</label>
        @php($selectedCustomer = old('customer_id', $page->customer_id ?? ''))
        <select name="customer_id" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
            <option value="">-- Sin asignar --</option>
            @foreach($customers as $customer)
                <option value="{{ $customer->id }}" @selected((string) $selectedCustomer === (string) $customer->id)>
                    {{ $customer->name }} (ID: {{ $customer->id }})
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block mb-1 text-white/70">Meta Page ID *</label>
        <input name="meta_page_id" value="{{ old('meta_page_id', $page->meta_page_id ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40" required>
    </div>

    <div>
        <label class="block mb-1 text-white/70">Nombre *</label>
        <input name="name" value="{{ old('name', $page->name ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40" required>
    </div>

    <div class="md:col-span-2">
        <label class="block mb-1 text-white/70">Page Access Token</label>
        <textarea name="page_access_token" rows="4"
                  class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40">{{ old('page_access_token', $page->page_access_token ?? '') }}</textarea>
    </div>

    <div class="md:col-span-2">
        <label class="block mb-1 text-white/70">Estado CRM</label>
        @php($status = (string) old('status', isset($page) ? (int) $page->status : 0))
        <select name="status" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
            <option value="1" @selected($status === '1')>Activa</option>
            <option value="0" @selected($status === '0')>Inactiva</option>
        </select>
    </div>

    <div>
        <label class="block mb-1 text-white/70">Leadgen</label>
        <input value="{{ $page->is_leadgen_subscribed ? 'Suscrita' : 'No suscrita' }}"
               disabled
               class="w-full rounded-xl border p-2 font-semibold {{ $page->is_leadgen_subscribed ? 'bg-emerald-500/15 border-emerald-300/30 text-emerald-200' : 'bg-rose-500/15 border-rose-300/30 text-rose-200' }}">
    </div>

    <div>
        <label class="block mb-1 text-white/70">Revision suscripcion</label>
        <input value="{{ optional($page->subscription_checked_at)->format('Y-m-d H:i') ?: 'Sin validar' }}"
               disabled
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/40 text-white/70">
    </div>

    <div class="md:col-span-2">
        <label class="block mb-1 text-white/70">leadgen</label>
        <textarea rows="4" disabled
                  class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/40 text-white/70">{{ $page->leadgen ? json_encode($page->leadgen, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'Sin respuesta registrada' }}</textarea>
    </div>
</div>
