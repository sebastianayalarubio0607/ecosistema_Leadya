<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="md:col-span-2">
        <label class="block mb-1 text-white/70">Ad Account (Customer → Account)</label>
        @php($selected = old('meta_ad_account_id', $campaign->meta_ad_account_id ?? ''))
        <select name="meta_ad_account_id" required
                class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
            <option value="">-- Seleccionar --</option>
            @foreach($accounts as $a)
                <option value="{{ $a->id }}" @selected((string)$selected === (string)$a->id)>
                    {{ $a->customer?->name ?? '—' }} — {{ $a->name }} ({{ $a->meta_account_id }})
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block mb-1 text-white/70">Meta Campaign ID</label>
        <input name="meta_campaign_id"
               value="{{ old('meta_campaign_id', $campaign->meta_campaign_id ?? '') }}"
               required
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>

    <div>
        <label class="block mb-1 text-white/70">Name</label>
        <input name="name"
               value="{{ old('name', $campaign->name ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>

    <div>
        <label class="block mb-1 text-white/70">Objective</label>
        <input name="objective"
               value="{{ old('objective', $campaign->objective ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>

    <div>
        <label class="block mb-1 text-white/70">Buying Type</label>
        <input name="buying_type"
               value="{{ old('buying_type', $campaign->buying_type ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>

    <div class="md:col-span-2">
        <label class="block mb-1 text-white/70">Status</label>
        @php($val = old('status', $campaign->status ?? 'active'))
        <select name="status" required
                class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
            <option value="active" @selected($val==='active')>active</option>
            <option value="inactive" @selected($val==='inactive')>inactive</option>
        </select>
    </div>
</div>
