<div class="contents">
    <div class="md:col-span-2">
        <label class="mb-1 block text-white/70">Cliente</label>
        <select name="customer_id" wire:model.live="customerId" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white">
            <option value="">Todos</option>
            @foreach($customers as $customer)
                <option value="{{ $customer->id }}">{{ $customer->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="md:col-span-2">
        <label class="mb-1 block text-white/70">Tipo</label>
        <select name="integrationtype_id" wire:model.live="typeId" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white">
            <option value="">Todos</option>
            @foreach($types as $type)
                <option value="{{ $type->id }}">{{ $type->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="md:col-span-3">
        <label class="mb-1 block text-white/70">Integración</label>
        <select name="integration_id" wire:model="integrationId" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white">
            <option value="">{{ $customerId || $typeId ? 'Todas las coincidencias' : 'Todas' }}</option>
            @foreach($integrations as $integration)
                <option value="{{ $integration->id }}">
                    {{ $integration->name }} @if($integration->customer) / {{ $integration->customer->name }} @endif
                </option>
            @endforeach
        </select>
    </div>
</div>
