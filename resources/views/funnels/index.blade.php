@extends('meta.layout')

@section('title', 'Funnels')
@section('subtitle', 'Listado de Funnels (asociación: Funnel → Meta Event)')

@section('header_actions')
    <a href="{{ route('funnels.create') }}"
       class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">
        + Nuevo
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">

        @if (session('success'))
            <div class="rounded-xl border border-emerald-300/20 bg-emerald-500/10 text-emerald-200 px-4 py-3">
                {{ session('success') }}
            </div>
        @endif

        <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <div class="md:col-span-10">
                <label class="block mb-1 text-white/70">Buscar</label>
                <input name="q" value="{{ $q ?? request('q') }}"
                       class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
                       placeholder="Buscar por nombre">
            </div>

            <div class="md:col-span-2 flex gap-2">
                <button class="w-full px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
                    Buscar
                </button>
                <a href="{{ route('funnels.index') }}"
                   class="w-full text-center px-4 py-2 rounded-xl bg-zinc-950/25 hover:bg-white/10 text-white border border-white/10">
                    Limpiar
                </a>
            </div>
        </form>

        <div class="overflow-x-auto rounded-xl border border-white/10">
            <table class="min-w-full text-sm">
                <thead class="bg-white/5 text-white/70">
                    <tr>
                        <th class="text-left px-3 py-2">ID</th>
                        <th class="text-left px-3 py-2">Nombre</th>
                        <th class="text-left px-3 py-2">Meta Event</th>
                        <th class="text-left px-3 py-2">Estado</th>
                        <th class="text-left px-3 py-2">Qualifications</th>
                        <th class="text-left px-3 py-2 w-56">Acciones</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-white/10 text-white/80">
                    @forelse($funnels as $funnel)
                        @php
                            $meta = $funnel->metaEvent ?? null;

                            // ✅ Nombre del Meta Event (ajusta si tu columna real es 'nombre')
                            $metaLabel = $meta->nombre
                                ?? $meta->name
                                ?? $meta->event_name
                                ?? $meta->title
                                ?? $meta->event
                                ?? $meta->nombre_evento
                                ?? null;
                        @endphp

                        <tr class="hover:bg-white/5">
                            <td class="px-3 py-2">{{ $funnel->id }}</td>
                            <td class="px-3 py-2">{{ $funnel->name }}</td>

                            <td class="px-3 py-2">
                                
                                @if($funnel->meta_event_id)
                                    <span class="px-2 py-1 rounded-lg bg-white/10 border border-white/10 text-xs">
                                        {{ $metaLabel ?: ('ID: ' . $funnel->meta_event_id) }}
                                    </span>
                                @else
                                    <span class="text-white/50">—</span>
                                @endif
                            </td>

                            <td class="px-3 py-2">{{ $funnel->status }}</td>
                            <td class="px-3 py-2">{{ $funnel->qualifications_count ?? 0 }}</td>

                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <a class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs"
                                       href="{{ route('funnels.show', $funnel) }}">Ver</a>

                                    <a class="px-3 py-1.5 rounded-lg bg-indigo-500/20 hover:bg-indigo-500/30 border border-white/10 text-xs"
                                       href="{{ route('funnels.edit', $funnel) }}">Editar</a>

                                    <form action="{{ route('funnels.destroy', $funnel) }}" method="POST">
                                        @csrf @method('DELETE')
                                        <button class="px-3 py-1.5 rounded-lg bg-rose-500/20 hover:bg-rose-500/30 border border-rose-300/20 text-xs"
                                                onclick="return confirm('¿Eliminar Funnel?')">
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-8 text-center text-white/60">No hay funnels.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $funnels->links() }}</div>
    </div>
@endsection
