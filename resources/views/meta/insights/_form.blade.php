<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block mb-1 text-white/70">account_id</label>
        <input name="account_id" value="{{ old('account_id', $insight->account_id ?? '') }}" required
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>
    <div>
        <label class="block mb-1 text-white/70">account_name</label>
        <input name="account_name" value="{{ old('account_name', $insight->account_name ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>

    <div>
        <label class="block mb-1 text-white/70">campaign_id</label>
        <input name="campaign_id" value="{{ old('campaign_id', $insight->campaign_id ?? '') }}" required
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>
    <div>
        <label class="block mb-1 text-white/70">campaign_name</label>
        <input name="campaign_name" value="{{ old('campaign_name', $insight->campaign_name ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>

    <div>
        <label class="block mb-1 text-white/70">adset_id</label>
        <input name="adset_id" value="{{ old('adset_id', $insight->adset_id ?? '') }}" required
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>
    <div>
        <label class="block mb-1 text-white/70">adset_name</label>
        <input name="adset_name" value="{{ old('adset_name', $insight->adset_name ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>

    <div>
        <label class="block mb-1 text-white/70">ad_id</label>
        <input name="ad_id" value="{{ old('ad_id', $insight->ad_id ?? '') }}" required
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>
    <div>
        <label class="block mb-1 text-white/70">ad_name</label>
        <input name="ad_name" value="{{ old('ad_name', $insight->ad_name ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>

    <div>
        <label class="block mb-1 text-white/70">objective</label>
        <input name="objective" value="{{ old('objective', $insight->objective ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>
    <div>
        <label class="block mb-1 text-white/70">optimization_goal</label>
        <input name="optimization_goal" value="{{ old('optimization_goal', $insight->optimization_goal ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>

    <div>
        <label class="block mb-1 text-white/70">buying_type</label>
        <input name="buying_type" value="{{ old('buying_type', $insight->buying_type ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>
    <div>
        <label class="block mb-1 text-white/70">attribution_setting</label>
        <input name="attribution_setting" value="{{ old('attribution_setting', $insight->attribution_setting ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>

    <div>
        <label class="block mb-1 text-white/70">impressions</label>
        <input name="impressions" value="{{ old('impressions', $insight->impressions ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>
    <div>
        <label class="block mb-1 text-white/70">reach</label>
        <input name="reach" value="{{ old('reach', $insight->reach ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>

    <div>
        <label class="block mb-1 text-white/70">frequency</label>
        <input name="frequency" value="{{ old('frequency', $insight->frequency ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>
    <div>
        <label class="block mb-1 text-white/70">spend</label>
        <input name="spend" value="{{ old('spend', $insight->spend ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>

    <div>
        <label class="block mb-1 text-white/70">clicks</label>
        <input name="clicks" value="{{ old('clicks', $insight->clicks ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>
    <div>
        <label class="block mb-1 text-white/70">unique_clicks</label>
        <input name="unique_clicks" value="{{ old('unique_clicks', $insight->unique_clicks ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>

    <div>
        <label class="block mb-1 text-white/70">inline_link_clicks</label>
        <input name="inline_link_clicks" value="{{ old('inline_link_clicks', $insight->inline_link_clicks ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>
    <div>
        <label class="block mb-1 text-white/70">cpm</label>
        <input name="cpm" value="{{ old('cpm', $insight->cpm ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>

    <div>
        <label class="block mb-1 text-white/70">date_start</label>
        <input type="date" name="date_start" value="{{ old('date_start', optional($insight->date_start)->format('Y-m-d')) }}" required
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>
    <div>
        <label class="block mb-1 text-white/70">date_stop</label>
        <input type="date" name="date_stop" value="{{ old('date_stop', optional($insight->date_stop)->format('Y-m-d')) }}" required
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>

    <div class="md:col-span-2">
        <label class="block mb-1 text-white/70">actions (JSON)</label>
        <textarea name="actions" rows="3"
                  class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white"
                  placeholder='[{"action_type":"lead","value":"10"}]'>{{ old('actions', isset($insight) ? json_encode($insight->actions, JSON_UNESCAPED_UNICODE) : '') }}</textarea>
        <div class="text-xs text-white/50 mt-1">Tip: pega un JSON válido (array) para que se guarde en la columna JSON.</div>
    </div>

    <div class="md:col-span-2">
        <label class="block mb-1 text-white/70">Status</label>
        @php($val = old('status', $insight->status ?? 'active'))
        <select name="status" required
                class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
            <option value="active" @selected($val==='active')>active</option>
            <option value="inactive" @selected($val==='inactive')>inactive</option>
        </select>
    </div>
</div>
