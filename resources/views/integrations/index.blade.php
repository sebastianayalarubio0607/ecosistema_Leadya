@extends('meta.layout')

@section('title', 'Integrations')
@section('subtitle', 'Integraciones activas por cliente, tipo y credencial pública')

@section('header_actions')
    <a href="{{ route('integrations.create') }}"
       class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">
        + Nuevo
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <div class="md:col-span-10">
                <label class="block mb-1 text-white/70">Buscar</label>
                <input name="q" value="{{ $q ?? request('q') }}"
                       class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
                       placeholder="Buscar por nombre, url o public_key..." />
            </div>

            <div class="md:col-span-2 flex gap-2">
                <button class="w-full px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
                    Buscar
                </button>
                <a href="{{ route('integrations.index') }}"
                   class="w-full text-center px-4 py-2 rounded-xl bg-zinc-950/25 hover:bg-white/10 text-white border border-white/10">
                    Limpiar
                </a>
            </div>
        </form>

        <div class="overflow-x-auto rounded-xl border border-white/10">
            <table class="min-w-full text-sm">
                <thead class="bg-white/5 text-white/70">
                    <tr>
                        <th class="text-left px-3 py-2">Nombre</th>
                        <th class="text-left px-3 py-2">Cliente</th>
                        <th class="text-left px-3 py-2">Tipo</th>
                        <th class="text-left px-3 py-2">Status</th>
                        <th class="text-left px-3 py-2">Public Key</th>
                        <th class="text-left px-3 py-2 w-72">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10 text-white/80">
                    @forelse ($integrations as $integration)
                        <tr class="hover:bg-white/5">
                            <td class="px-3 py-2">{{ $integration->name }}</td>
                            <td class="px-3 py-2">{{ $integration->customer->name ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $integration->integrationtype->name ?? '—' }}</td>
                            <td class="px-3 py-2">
                                <span class="px-2 py-1 rounded-lg text-xs border {{ (int) $integration->status === 1 ? 'bg-emerald-500/10 border-emerald-300/20 text-emerald-200' : 'bg-white/10 border-white/10 text-white/70' }}">
                                    {{ (int) $integration->status === 1 ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-3 py-2 font-mono text-xs">
                                {{ $integration->public_key ?? '—' }}
                            </td>

                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <a class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs"
                                       href="{{ route('integrations.show', $integration) }}">
                                        Ver
                                    </a>

                                    <a class="px-3 py-1.5 rounded-lg bg-indigo-500/20 hover:bg-indigo-500/30 border border-white/10 text-xs"
                                       href="{{ route('integrations.edit', $integration) }}">
                                        Editar
                                    </a>

                                    <form method="POST"
                                          action="{{ route('integrations.destroy', $integration) }}"
                                          onsubmit="return confirm('¿Seguro que deseas eliminar esta integración?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="px-3 py-1.5 rounded-lg bg-rose-500/20 hover:bg-rose-500/30 border border-rose-300/20 text-xs" type="submit">
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-3 py-8 text-center text-white/60" colspan="6">No hay integraciones.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $integrations->links() }}</div>
    </div>
@endsection
