<form wire:submit="applyFilters" class="grid grid-cols-1 items-end gap-3 md:grid-cols-12">
    <div class="md:col-span-3">
        <label class="mb-1 block text-white/70">Buscar</label>
        <input wire:model="q" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white placeholder-white/40" placeholder="Buscar por ID o nombre">
    </div>

    <div class="md:col-span-2">
        <label class="mb-1 block text-white/70">Nombre</label>
        <input wire:model="name" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white placeholder-white/40" placeholder="Nombre CRM State">
    </div>

    <div class="md:col-span-2">
        <label class="mb-1 block text-white/70">Cliente</label>
        <select wire:model.live="customerId" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white">
            <option value="">Todos</option>
            @foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}</option>@endforeach
        </select>
    </div>

    <div class="md:col-span-2">
        <label class="mb-1 block text-white/70">Tipo</label>
        <select wire:model.live="typeId" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white">
            <option value="">Todos</option>
            @foreach($types as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach
        </select>
    </div>

    <div class="md:col-span-3">
        <label class="mb-1 block text-white/70">Integration</label>
        <select wire:model.live="integrationId" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white">
            <option value="">{{ $customerId !== '' || $typeId !== '' ? 'Integraciones coincidentes' : 'Todas' }}</option>
            @foreach($integrations as $integration)
                <option value="{{ $integration->id }}">{{ $integration->name }} @if($integration->customer) / {{ $integration->customer->name }} @endif</option>
            @endforeach
        </select>
    </div>

    <div class="md:col-span-2">
        <label class="mb-1 block text-white/70">Sin gestionar</label>
        <select wire:model="unmanaged" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white">
            <option value="">Todos</option><option value="1">Sí</option><option value="0">No</option>
        </select>
    </div>

    <div class="md:col-span-2">
        <label class="mb-1 block text-white/70">Qualification</label>
        <select wire:model="qualificationId" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white">
            <option value="">Todas</option>
            @foreach($qualifications as $qualification)<option value="{{ $qualification->id }}">{{ $qualification->name }}</option>@endforeach
        </select>
    </div>

    <div class="md:col-span-2">
        <label class="mb-1 block text-white/70">Pipeline</label>
        <select wire:model="pipelineId" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white">
            <option value="">Todos</option><option value="none">Sin pipeline</option>
            @foreach($pipelines as $pipeline)<option value="{{ $pipeline->id }}">{{ $pipeline->name }}</option>@endforeach
        </select>
    </div>

    <div class="md:col-span-2">
        <label class="mb-1 block text-white/70">Meta Event</label>
        <select wire:model="metaEventId" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white">
            <option value="">Todos</option><option value="none">Sin Meta Event</option>
            @foreach($metaEvents as $metaEvent)<option value="{{ $metaEvent->id }}">{{ $metaEvent->nombre }}</option>@endforeach
        </select>
    </div>

    <div class="md:col-span-2">
        <label class="mb-1 block text-white/70">WhatsApp Event</label>
        <select wire:model="whatsappEventId" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white">
            <option value="">Todos</option><option value="none">Sin WhatsApp Event</option>
            @foreach($whatsappEvents as $whatsappEvent)<option value="{{ $whatsappEvent->id }}">{{ $whatsappEvent->event_name }}</option>@endforeach
        </select>
    </div>

    <div class="md:col-span-2">
        <label class="mb-1 block text-white/70">Google Ads</label>
        <select wire:model="googleAdsEnabled" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white">
            <option value="">Todos</option><option value="1">Activa</option><option value="0">Inactiva</option>
        </select>
    </div>

    <div class="flex gap-2 md:col-span-4">
        <button wire:loading.attr="disabled" class="w-full rounded-xl border border-white/10 bg-white/10 px-4 py-2 text-white hover:bg-white/15 disabled:opacity-50">Filtrar</button>
        <button type="button" wire:click="clearFilters" wire:loading.attr="disabled" class="w-full rounded-xl border border-white/10 bg-zinc-950/25 px-4 py-2 text-center text-white hover:bg-white/10 disabled:opacity-50">Limpiar</button>
    </div>
</form>
