@extends('meta.layout')

@section('title', 'Geos')
@section('subtitle', 'Catálogo geográfico para el generador de URLs')

@section('header_actions')
    <a href="{{ route('geos.create') }}"
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
                       placeholder="Buscar por código o nombre">
            </div>

            <div class="md:col-span-2 flex gap-2">
                <button class="w-full px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
                    Buscar
                </button>
                <a href="{{ route('geos.index') }}"
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
                        <th class="text-left px-3 py-2">Código</th>
                        <th class="text-left px-3 py-2">Nombre</th>
                        <th class="text-left px-3 py-2">Estado</th>
                        <th class="text-left px-3 py-2">Actualizado</th>
                        <th class="text-left px-3 py-2 w-56">Acciones</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-white/10 text-white/80">
                    @forelse($geos as $geo)
                        <tr class="hover:bg-white/5">
                            <td class="px-3 py-2">{{ $geo->id }}</td>
                            <td class="px-3 py-2">
                                <span class="px-2 py-1 rounded-lg bg-white/10 border border-white/10 text-xs">{{ $geo->code }}</span>
                            </td>
                            <td class="px-3 py-2">{{ $geo->name }}</td>
                            <td class="px-3 py-2">
                                <span class="px-2 py-1 rounded-lg text-xs border {{ $geo->is_active ? 'bg-emerald-500/10 border-emerald-300/20 text-emerald-200' : 'bg-white/10 border-white/10 text-white/70' }}">
                                    {{ $geo->is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-3 py-2">{{ optional($geo->updated_at)->format('Y-m-d H:i') }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <a class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs"
                                       href="{{ route('geos.show', $geo) }}">Ver</a>
                                    <a class="px-3 py-1.5 rounded-lg bg-indigo-500/20 hover:bg-indigo-500/30 border border-white/10 text-xs"
                                       href="{{ route('geos.edit', $geo) }}">Editar</a>
                                    <form action="{{ route('geos.destroy', $geo) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button class="px-3 py-1.5 rounded-lg bg-rose-500/20 hover:bg-rose-500/30 border border-rose-300/20 text-xs"
                                                onclick="return confirm('¿Eliminar geo?')">
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-8 text-center text-white/60">No hay geos registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $geos->links() }}</div>
    </div>
@endsection
