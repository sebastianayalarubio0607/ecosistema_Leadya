<form wire:submit="save" class="rounded-2xl border border-white/10 bg-zinc-950/25 p-4 text-white backdrop-blur space-y-4">
    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
        <div>
            <label for="pipeline-customer" class="mb-1 block text-white/70">Cliente</label>
            <select id="pipeline-customer" wire:model.live="customerId" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white">
                <option value="">Selecciona un cliente</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                @endforeach
            </select>
            @error('customerId')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="pipeline-integration" class="mb-1 block text-white/70">Integración</label>
            <select id="pipeline-integration" wire:model="integrationId" @disabled($customerId === '') class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white disabled:cursor-not-allowed disabled:opacity-50">
                <option value="">{{ $customerId === '' ? 'Primero selecciona un cliente' : 'Selecciona una integración' }}</option>
                @foreach($integrations as $integration)
                    <option value="{{ $integration->id }}">
                        {{ $integration->name }}{{ $integration->integrationtype?->name ? ' · '.$integration->integrationtype->name : '' }}
                    </option>
                @endforeach
            </select>
            @error('integrationId')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
            @if($customerId !== '' && $integrations->isEmpty())
                <p class="mt-1 text-xs text-amber-200/80">Este cliente no tiene integraciones disponibles.</p>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
        <div>
            <label for="pipeline-external-id" class="mb-1 block text-white/70">ID externo</label>
            <input id="pipeline-external-id" wire:model="externalId" maxlength="255" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white placeholder-white/40" placeholder="ID del pipeline en el CRM">
            @error('externalId')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="pipeline-name" class="mb-1 block text-white/70">Nombre</label>
            <input id="pipeline-name" wire:model="name" maxlength="255" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white placeholder-white/40" placeholder="Nombre del pipeline">
            @error('name')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="flex justify-end gap-2 border-t border-white/10 pt-4">
        <a href="{{ route('pipelines.index') }}" class="rounded-xl border border-white/10 bg-white/10 px-4 py-2 hover:bg-white/15">Cancelar</a>
        <button wire:loading.attr="disabled" class="rounded-xl border border-white/10 bg-indigo-500/30 px-4 py-2 hover:bg-indigo-500/40 disabled:opacity-50">
            <span wire:loading.remove>Crear Pipeline</span>
            <span wire:loading>Guardando…</span>
        </button>
    </div>
</form>
