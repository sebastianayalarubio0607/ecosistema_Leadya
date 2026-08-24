@extends('meta.layout')

@section('title', 'Conector IA')
@section('subtitle', $connector->name)

@section('header_actions')
    <a href="{{ route('ai-connectors.edit', $connector) }}"
       class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">
        Editar
    </a>
    <a href="{{ route('ai-connectors.index') }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        Volver
    </a>
@endsection

@section('content')
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
        <section class="rounded-2xl border border-white/10 bg-zinc-950/25 p-4 backdrop-blur xl:col-span-7 space-y-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <p class="text-sm text-white/50">Estado</p>
                    <p class="mt-1 font-semibold {{ $connector->is_active ? 'text-emerald-200' : 'text-white/60' }}">{{ $connector->is_active ? 'Activo' : 'Inactivo' }}</p>
                </div>
                <div>
                    <p class="text-sm text-white/50">Cliente</p>
                    <p class="mt-1 font-semibold text-white">{{ $connector->customer?->name ?? 'Todos los clientes' }}</p>
                </div>
                <div>
                    <p class="text-sm text-white/50">Client ID</p>
                    <p class="mt-1 break-all rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white">{{ $connector->client_id }}</p>
                </div>
                <div>
                    <p class="text-sm text-white/50">Contraseña</p>
                    <p class="mt-1 rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white">{{ $connector->maskedClientSecret() }}</p>
                </div>
            </div>

            @if(session('revealed_secret'))
                <div class="rounded-2xl border border-amber-300/20 bg-amber-500/10 p-4">
                    <p class="text-sm font-semibold text-amber-100">Contraseña descifrada</p>
                    <input readonly value="{{ session('revealed_secret') }}"
                           class="mt-2 w-full rounded-xl border border-amber-300/20 bg-slate-950/80 p-2 font-mono text-sm text-amber-50">
                    <p class="mt-2 text-xs text-amber-100/70">Se muestra solo en esta respuesta. Trátala como secreto de producción.</p>
                </div>
            @endif

            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('ai-connectors.reveal-secret', $connector) }}">
                    @csrf
                    <button class="rounded-xl border border-white/10 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/15">
                        Ver contraseña
                    </button>
                </form>
                <form method="POST" action="{{ route('ai-connectors.rotate-secret', $connector) }}" onsubmit="return confirm('Restablecer la contraseña revocara los access tokens emitidos. Continuar?')">
                    @csrf
                    <button class="rounded-xl border border-rose-300/20 bg-rose-500/20 px-4 py-2 text-sm font-semibold text-rose-100 hover:bg-rose-500/30">
                        Restablecer contraseña
                    </button>
                </form>
            </div>

            <div>
                <h3 class="mb-2 text-lg font-semibold text-white">Endpoints</h3>
                <div class="space-y-2 text-sm">
                    <p class="break-all rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-white/80"><span class="text-white/50">MCP:</span> {{ $mcpEndpoint }}</p>
                    <p class="break-all rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-white/80"><span class="text-white/50">Token:</span> {{ $tokenEndpoint }}</p>
                    <p class="break-all rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-white/80"><span class="text-white/50">Metadata:</span> {{ $metadataEndpoint }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-white/10 bg-zinc-950/25 p-4 backdrop-blur xl:col-span-5 space-y-4">
            <h3 class="text-lg font-semibold text-white">Restricciones</h3>
            <div class="grid grid-cols-2 gap-3 text-sm text-white/80">
                <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                    <p class="text-white/50">Por minuto</p>
                    <p class="mt-1 text-xl font-bold text-white">{{ $connector->max_requests_per_minute }}</p>
                </div>
                <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                    <p class="text-white/50">Por dia</p>
                    <p class="mt-1 text-xl font-bold text-white">{{ $connector->max_requests_per_day }}</p>
                </div>
                <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                    <p class="text-white/50">Rango dias</p>
                    <p class="mt-1 text-xl font-bold text-white">{{ $connector->max_date_range_days }}</p>
                </div>
                <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                    <p class="text-white/50">Cache</p>
                    <p class="mt-1 text-xl font-bold text-white">{{ $connector->cache_ttl_seconds }}s</p>
                </div>
            </div>

            <div>
                <p class="mb-2 text-sm text-white/50">Herramientas habilitadas</p>
                <div class="space-y-2">
                    @foreach($connector->allowedToolNames() as $tool)
                        <div class="rounded-xl border border-white/10 bg-white/5 px-3 py-2">
                            <p class="text-sm font-semibold text-white">{{ $tools[$tool] ?? $tool }}</p>
                            <p class="text-xs text-white/50">{{ $tool }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    </div>

    <section class="mt-4 rounded-2xl border border-white/10 bg-zinc-950/25 p-4 backdrop-blur">
        <h3 class="mb-3 text-lg font-semibold text-white">Auditoria reciente</h3>
        <div class="overflow-x-auto rounded-xl border border-white/10">
            <table class="min-w-full text-sm">
                <thead class="bg-white/5 text-white/70">
                    <tr>
                        <th class="text-left px-3 py-2">Fecha</th>
                        <th class="text-left px-3 py-2">Herramienta</th>
                        <th class="text-left px-3 py-2">Estado</th>
                        <th class="text-left px-3 py-2">Cache</th>
                        <th class="text-left px-3 py-2">Duracion</th>
                        <th class="text-left px-3 py-2">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10 text-white/80">
                    @forelse($connector->queryLogs as $log)
                        <tr class="hover:bg-white/5">
                            <td class="px-3 py-2">{{ optional($log->created_at)->format('Y-m-d H:i:s') }}</td>
                            <td class="px-3 py-2">{{ $log->tool_name }}</td>
                            <td class="px-3 py-2">{{ $log->status }}</td>
                            <td class="px-3 py-2">{{ $log->cache_hit ? 'Si' : 'No' }}</td>
                            <td class="px-3 py-2">{{ $log->duration_ms }} ms</td>
                            <td class="px-3 py-2">{{ $log->ip_address }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-8 text-center text-white/60">Sin consultas registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
