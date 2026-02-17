<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="md:col-span-2">
        <label class="block mb-1 text-white/70">Campaign (Customer → Account → Campaign)</label>
        @php($selected = old('meta_campaign_id', $ad_set->meta_campaign_id ?? ''))
        <select name="meta_campaign_id" required
                class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
            <option value="">-- Seleccionar --</option>
            @foreach($campaigns as $c)
                <option value="{{ $c->id }}" @selected((string)$selected === (string)$c->id)>
                    {{ $c->account?->customer?->name ?? '—' }} — {{ $c->account?->name ?? '—' }} — {{ $c->name }} ({{ $c->meta_campaign_id }})
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block mb-1 text-white/70">Meta Ad Set ID (adset_id)</label>
        <input name="meta_ad_set_id"
               value="{{ old('meta_ad_set_id', $ad_set->meta_ad_set_id ?? '') }}"
               required
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>

    <div>
        <label class="block mb-1 text-white/70">Name (adset_name)</label>
        <input name="name"
               value="{{ old('name', $ad_set->name ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>

    <div>
        <label class="block mb-1 text-white/70">Optimization Goal</label>
        <input name="optimization_goal"
               value="{{ old('optimization_goal', $ad_set->optimization_goal ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>

    <div>
        <label class="block mb-1 text-white/70">Attribution Setting</label>
        <input name="attribution_setting"
               value="{{ old('attribution_setting', $ad_set->attribution_setting ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>

    <div class="md:col-span-2">
        <label class="block mb-1 text-white/70">Status</label>
        @php($val = old('status', $ad_set->status ?? 'active'))
        <select name="status" required
                class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
            <option value="active" @selected($val==='active')>active</option>
            <option value="inactive" @selected($val==='inactive')>inactive</option>
        </select>
    </div>
</div>
