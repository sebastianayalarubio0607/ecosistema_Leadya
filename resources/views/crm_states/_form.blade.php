@php
    /** @var \App\Models\CrmState|null $crmstate */
    $crmstate = $crmstate ?? null;

    // create: se usa integration_id + external_id
    $isCreate = $isCreate ?? false;
@endphp

<div class="space-y-4">
    @if($isCreate)
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
            <div class="md:col-span-7">
                <label class="block mb-1 text-white/70">Integración</label>
                <select name="integration_id"
                        class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">— Selecciona —</option>
                    @foreach($integrations as $in)
                        <option value="{{ $in->id }}" @selected(old('integration_id') == $in->id)>
                            {{ $in->customer?->name ?? '—' }} — {{ $in->name }} ({{ $in->id }})
                        </option>
                    @endforeach
                </select>
                @error('integration_id') <div class="text-rose-300 text-xs mt-1">{{ $message }}</div> @enderror
            </div>

            <div class="md:col-span-5">
                <label class="block mb-1 text-white/70">External ID (del CRM)</label>
                <input name="external_id" value="{{ old('external_id') }}"
                       class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
                       placeholder="Ej: 123, WON, status_1">
                @error('external_id') <div class="text-rose-300 text-xs mt-1">{{ $message }}</div> @enderror
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
            <div class="md:col-span-6">
                <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                    <div class="text-xs text-white/50">Integration ID</div>
                    <div class="text-white">{{ $integrationId ?? '—' }}</div>
                    <div class="text-xs text-white/50 mt-2">Customer / Integration</div>
                    <div class="text-white">
                        {{ $integration?->customer?->name ?? '—' }} — {{ $integration?->name ?? '—' }}
                    </div>
                </div>
            </div>

            <div class="md:col-span-6">
                <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                    <div class="text-xs text-white/50">External ID</div>
                    <div class="text-white">{{ $externalId ?? '—' }}</div>
                    <div class="text-xs text-white/50 mt-2">CRM State ID</div>
                    <div class="text-white">{{ $crmstate?->id ?? '—' }}</div>
                </div>
            </div>
        </div>
    @endif

    <div>
        <label class="block mb-1 text-white/70">Nombre</label>
        <input name="name" value="{{ old('name', $crmstate?->name) }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
               placeholder="Ej: Nuevo, Contactado, Ganado, Perdido">
        @error('name') <div class="text-rose-300 text-xs mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
        <div class="md:col-span-6">
            <label class="block mb-1 text-white/70">Qualification</label>
            <select name="qualification"
                    class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                <option value="">— Selecciona —</option>
                @foreach($qualifications as $q)
                    <option value="{{ $q->id }}" @selected(old('qualification', $crmstate?->qualification) == $q->id)>
                        {{ $q->name }}
                    </option>
                @endforeach
            </select>
            @error('qualification') <div class="text-rose-300 text-xs mt-1">{{ $message }}</div> @enderror
        </div>

        <div class="md:col-span-6">
            <label class="block mb-1 text-white/70">Meta Event (Conversión)</label>
            <select name="meta_event_id"
                    class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                <option value="">— Sin asignar —</option>
                @foreach($metaEvents as $me)
                    <option value="{{ $me->id }}" @selected(old('meta_event_id', $crmstate?->meta_event_id) == $me->id)>
                        {{ $me->nombre }} ({{ $me->estados }})
                    </option>
                @endforeach
            </select>
            @error('meta_event_id') <div class="text-rose-300 text-xs mt-1">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="flex gap-2">
        <button class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">
            {{ $submitText ?? 'Guardar' }}
        </button>

        <a href="{{ $cancelUrl ?? route('crmstates.index') }}"
           class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
            Cancelar
        </a>
    </div>
</div>
