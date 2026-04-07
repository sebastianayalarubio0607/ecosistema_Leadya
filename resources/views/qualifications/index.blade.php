@extends('meta.layout')

@section('title', 'Qualifications')
@section('subtitle', 'Listado de calificaciones y su relación con funnels')

@section('header_actions')
    <a href="{{ route('qualifications.create') }}"
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
                       placeholder="Buscar por nombre..." />
            </div>

            <div class="md:col-span-2 flex gap-2">
                <button class="w-full px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
                    Buscar
                </button>
                <a href="{{ route('qualifications.index') }}"
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
                        <th class="text-left px-3 py-2">Creado</th>
                        <th class="text-left px-3 py-2 w-72">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10 text-white/80">
                    @forelse ($qualifications as $qualification)
                        <tr class="hover:bg-white/5">
                            <td class="px-3 py-2">{{ $qualification->id }}</td>
                            <td class="px-3 py-2">{{ $qualification->name }}</td>
                            <td class="px-3 py-2">{{ optional($qualification->created_at)->format('Y-m-d H:i') }}</td>

                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <a class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs"
                                       href="{{ route('qualifications.show', $qualification) }}">
                                        Ver
                                    </a>

                                    <a class="px-3 py-1.5 rounded-lg bg-indigo-500/20 hover:bg-indigo-500/30 border border-white/10 text-xs"
                                       href="{{ route('qualifications.edit', $qualification) }}">
                                        Editar
                                    </a>

                                    <form method="POST"
                                          action="{{ route('qualifications.destroy', $qualification) }}"
                                          onsubmit="return confirm('Â¿Seguro que deseas eliminar esta qualification?');">
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
                            <td class="px-3 py-8 text-center text-white/60" colspan="4">No hay qualifications.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $qualifications->links() }}</div>
    </div>
@endsection
