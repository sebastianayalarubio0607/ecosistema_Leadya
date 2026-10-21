<div class="rounded-2xl border border-white/10 bg-zinc-950/25 p-4 text-white backdrop-blur space-y-4">
    @if (session('success'))
        <div class="rounded-xl border border-emerald-300/20 bg-emerald-400/10 p-3 text-emerald-100">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 items-end gap-3 md:grid-cols-12">
        <div class="md:col-span-3">
            <label class="mb-1 block text-white/70">Buscar</label>
            <input wire:model.live.debounce.300ms="search" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white placeholder-white/40" placeholder="Buscar por ID o nombre">
        </div>

        <div class="md:col-span-2">
            <label class="mb-1 block text-white/70">Cliente</label>
            <select wire:model.live="customerId" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white"><option value="">Todos</option>
                @foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}</option>@endforeach
            </select>
        </div>

        <div class="md:col-span-2">
            <label class="mb-1 block text-white/70">Tipo</label>
            <select wire:model.live="typeId" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white"><option value="">Todos</option>
                @foreach($types as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach
            </select>
        </div>

        <div class="md:col-span-3">
            <label class="mb-1 block text-white/70">Integración</label>
            <select wire:model.live="integrationId" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white"><option value="">Todas</option>
                @foreach($integrations as $integration)<option value="{{ $integration->id }}">{{ $integration->name }} / {{ $integration->customer?->name ?? '—' }}</option>@endforeach
            </select>
        </div>

        <div class="md:col-span-2">
            <button wire:click="clearFilters" class="w-full rounded-xl border border-white/10 bg-zinc-950/25 px-4 py-2 hover:bg-white/10">Limpiar</button>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-white/10">
        <table class="min-w-full text-sm">
            <thead class="bg-white/5 text-white/70"><tr>
                <th class="px-3 py-2 text-left">ID</th>
                <th class="px-3 py-2 text-left">Nombre</th>
                <th class="px-3 py-2 text-left">ID externo</th>
                <th class="px-3 py-2 text-left">Cliente</th>
                <th class="px-3 py-2 text-left">Tipo</th>
                <th class="px-3 py-2 text-left">Integración</th>
                <th class="px-3 py-2 text-left">CRM States</th>
                <th class="w-56 px-3 py-2 text-left">Acciones</th>
            </tr></thead>
            <tbody class="divide-y divide-white/10 text-white/80">
                @forelse ($pipelines as $pipeline)
                    <tr wire:key="pipeline-{{ $pipeline->id }}" class="hover:bg-white/5">
                        <td class="px-3 py-2">{{ $pipeline->id }}</td>
                        <td class="px-3 py-2">{{ $pipeline->name }}</td>
                        <td class="px-3 py-2">{{ $pipeline->external_id }}</td>
                        <td class="px-3 py-2">{{ $pipeline->integration?->customer?->name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $pipeline->integration?->integrationtype?->name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $pipeline->integration?->name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $pipeline->crm_states_count }}</td>
                        <td class="px-3 py-2">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('pipelines.show', $pipeline) }}" class="rounded-lg border border-white/10 bg-white/10 px-3 py-1.5 text-xs hover:bg-white/15">Ver</a>
                                <a href="{{ route('pipelines.edit', $pipeline) }}" class="rounded-lg border border-white/10 bg-indigo-500/20 px-3 py-1.5 text-xs hover:bg-indigo-500/30">Editar</a>
                                <button wire:click="delete({{ $pipeline->id }})" wire:confirm="¿Eliminar Pipeline? Los CRM States asociados quedarán sin Pipeline." class="rounded-lg border border-rose-300/20 bg-rose-500/20 px-3 py-1.5 text-xs hover:bg-rose-500/30">Eliminar</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-3 py-8 text-center text-white/60">No hay Pipelines.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $pipelines->links() }}
</div>
