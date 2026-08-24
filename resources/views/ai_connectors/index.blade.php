@extends('meta.layout')

@section('title', 'Conectores IA')
@section('subtitle', 'Conectores MCP de solo lectura para datos agregados del dashboard general de leads')

@section('header_actions')
    <a href="{{ route('ai-connectors.create') }}"
       class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">
        + Nuevo conector
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <div class="md:col-span-10">
                <label class="block mb-1 text-white/70">Buscar</label>
                <input name="q" value="{{ $q }}"
                       class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
                       placeholder="Nombre o client_id">
            </div>
            <div class="md:col-span-2 flex gap-2">
                <button class="w-full px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
                    Buscar
                </button>
                <a href="{{ route('ai-connectors.index') }}"
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
                        <th class="text-left px-3 py-2">Client ID</th>
                        <th class="text-left px-3 py-2">Estado</th>
                        <th class="text-left px-3 py-2">Limites</th>
                        <th class="text-left px-3 py-2">Ultimo uso</th>
                        <th class="text-left px-3 py-2 w-44">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10 text-white/80">
                    @forelse($connectors as $connector)
                        <tr class="hover:bg-white/5">
                            <td class="px-3 py-2 font-semibold text-white">{{ $connector->name }}</td>
                            <td class="px-3 py-2">{{ $connector->customer?->name ?? 'Todos los clientes' }}</td>
                            <td class="px-3 py-2">
                                <span class="rounded-lg border border-white/10 bg-white/5 px-2 py-1 text-xs">{{ $connector->client_id }}</span>
                            </td>
                            <td class="px-3 py-2">
                                <span class="px-2 py-1 rounded-lg text-xs border {{ $connector->is_active ? 'bg-emerald-500/10 border-emerald-300/20 text-emerald-200' : 'bg-white/10 border-white/10 text-white/70' }}">
                                    {{ $connector->is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-xs text-white/70">
                                {{ $connector->max_requests_per_minute }}/min · {{ $connector->max_requests_per_day }}/dia
                            </td>
                            <td class="px-3 py-2">{{ optional($connector->last_used_at)->format('Y-m-d H:i') ?? 'Sin uso' }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <a class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs"
                                       href="{{ route('ai-connectors.show', $connector) }}">Ver</a>
                                    <a class="px-3 py-1.5 rounded-lg bg-indigo-500/20 hover:bg-indigo-500/30 border border-white/10 text-xs"
                                       href="{{ route('ai-connectors.edit', $connector) }}">Editar</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-8 text-center text-white/60">No hay conectores IA registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $connectors->links() }}</div>
    </div>
@endsection
