@extends('meta.layout')

@section('title', 'Meta Events')
@section('subtitle', 'Eventos de Meta (relación: Meta Event → Funnels)')

@section('header_actions')
    <a href="{{ route('meta.meta-events.create') }}"
       class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">
        + Nuevo
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <div class="md:col-span-10">
                <label class="block mb-1 text-white/70">Buscar</label>
                <input name="search" value="{{ request('search') }}"
                       class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
                       placeholder="Buscar por ID o nombre">
            </div>

            <div class="md:col-span-2 flex gap-2">
                <button class="w-full px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
                    Buscar
                </button>
                <a href="{{ route('meta.meta-events.index') }}"
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
                        <th class="text-left px-3 py-2">Estado</th>
<<<<<<< HEAD
                        <th class="text-left px-3 py-2">Funnels</th>
=======
                        <th class="text-left px-3 py-2">CRM States</th>
>>>>>>> 3ac2fef11dafeeab5dedfae1f504ba67206b2bba
                        <th class="text-left px-3 py-2 w-56">Acciones</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-white/10 text-white/80">
                    @forelse($items as $it)
                        <tr class="hover:bg-white/5">
                            <td class="px-3 py-2">{{ $it->id }}</td>
                            <td class="px-3 py-2">{{ $it->nombre }}</td>
                            <td class="px-3 py-2">{{ $it->estados }}</td>
<<<<<<< HEAD
                            <td class="px-3 py-2">{{ $it->funnels_count }}</td>
=======
                            <td class="px-3 py-2">{{ $it->crm_states_count ?? ($it->crmStates_count ?? '—') }}</td>
>>>>>>> 3ac2fef11dafeeab5dedfae1f504ba67206b2bba
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <a class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs"
                                       href="{{ route('meta.meta-events.show', $it) }}">Ver</a>

                                    <a class="px-3 py-1.5 rounded-lg bg-indigo-500/20 hover:bg-indigo-500/30 border border-white/10 text-xs"
                                       href="{{ route('meta.meta-events.edit', $it) }}">Editar</a>

                                    <form action="{{ route('meta.meta-events.destroy', $it) }}" method="POST">
                                        @csrf @method('DELETE')
                                        <button class="px-3 py-1.5 rounded-lg bg-rose-500/20 hover:bg-rose-500/30 border border-rose-300/20 text-xs"
                                                onclick="return confirm('¿Eliminar Meta Event?')">
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-8 text-center text-white/60">No hay meta events.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $items->links() }}</div>
    </div>
@endsection
