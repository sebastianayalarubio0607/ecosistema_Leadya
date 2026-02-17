@extends('meta.layout')

@section('title', 'CRM States')
@section('subtitle', 'Estados del CRM + asignación a Meta Event (conversión)')

@section('header_actions')
    <a href="{{ route('crmstates.create') }}"
       class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">
        + Nuevo
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <div class="md:col-span-10">
                <label class="block mb-1 text-white/70">Buscar</label>
                <input name="q" value="{{ $q }}"
                       class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
                       placeholder="Buscar por ID o nombre">
            </div>

            <div class="md:col-span-2 flex gap-2">
                <button class="w-full px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
                    Buscar
                </button>
                <a href="{{ route('crmstates.index') }}"
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
                        <th class="text-left px-3 py-2">Qualification</th>
                        <th class="text-left px-3 py-2">Meta Event</th>
                        <th class="text-left px-3 py-2 w-56">Acciones</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-white/10 text-white/80">
                    @forelse($crmstates as $it)
                        <tr class="hover:bg-white/5">
                            <td class="px-3 py-2">
                                {{ $it->id }}
                                <div class="text-xs text-white/50">
                                    Leads: {{ method_exists($it, 'leads') ? $it->leads()->count() : '—' }}
                                </div>
                            </td>
                            <td class="px-3 py-2">{{ $it->name }}</td>
                            <td class="px-3 py-2">{{ $it->qualificationModel?->name ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $it->metaEvent?->nombre ?? '—' }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <a class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs"
                                       href="{{ route('crmstates.show', $it) }}">Ver</a>

                                    <a class="px-3 py-1.5 rounded-lg bg-indigo-500/20 hover:bg-indigo-500/30 border border-white/10 text-xs"
                                       href="{{ route('crmstates.edit', $it) }}">Editar</a>

                                    <form action="{{ route('crmstates.destroy', $it) }}" method="POST">
                                        @csrf @method('DELETE')
                                        <button class="px-3 py-1.5 rounded-lg bg-rose-500/20 hover:bg-rose-500/30 border border-rose-300/20 text-xs"
                                                onclick="return confirm('¿Eliminar CRM State?')">
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-8 text-center text-white/60">No hay CRM States.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $crmstates->links() }}</div>
    </div>
@endsection
