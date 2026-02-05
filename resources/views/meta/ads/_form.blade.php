<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="md:col-span-2">
        <label class="block mb-1 text-white/70">Ad Set (Customer → Account → Campaign → Ad Set)</label>
        @php($selected = old('meta_ad_set_id', $ad->meta_ad_set_id ?? ''))
        <select name="meta_ad_set_id" required
                class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
            <option value="">-- Seleccionar --</option>
            @foreach($adSets as $s)
                <option value="{{ $s->id }}" @selected((string)$selected === (string)$s->id)>
                    {{ $s->campaign?->account?->customer?->name ?? '—' }}
                    — {{ $s->campaign?->name ?? '—' }}
                    — {{ $s->name }} ({{ $s->meta_ad_set_id }})
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block mb-1 text-white/70">Meta Ad ID (ad_id)</label>
        <input name="meta_ad_id"
               value="{{ old('meta_ad_id', $ad->meta_ad_id ?? '') }}"
               required
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>

    <div>
        <label class="block mb-1 text-white/70">Name (ad_name)</label>
        <input name="name"
               value="{{ old('name', $ad->name ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
    </div>

    <div class="md:col-span-2">
        <label class="block mb-1 text-white/70">Status</label>
        @php($val = old('status', $ad->status ?? 'active'))
        <select name="status" required
                class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
            <option value="active" @selected($val==='active')>active</option>
            <option value="inactive" @selected($val==='inactive')>inactive</option>
        </select>
    </div>
</div>
