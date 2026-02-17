<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="md:col-span-2">
        <label class="block mb-1 text-white/70">Cliente</label>
        @php($selectedCustomer = old('customer_id', $ad_account->customer_id ?? ''))
        <select name="customer_id" required
                class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
            <option value="">-- Seleccionar --</option>
            @foreach($customers as $c)
                <option value="{{ $c->id }}" @selected((string)$selectedCustomer === (string)$c->id)>
                    {{ $c->name }} (ID: {{ $c->id }})
                </option>
            @endforeach
        </select>
    </div>

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
</div>
