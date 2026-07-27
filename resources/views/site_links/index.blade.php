@extends('meta.layout')

@section('title', 'Site Links')
@section('subtitle', 'Catalogo de site links para el generador de URLs')

@section('header_actions')
    <a href="{{ route('site-links.create') }}"
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
                       placeholder="Buscar por codigo o valor">
            </div>

            <div class="md:col-span-2 flex gap-2">
                <button class="w-full px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
                    Buscar
                </button>
                <a href="{{ route('site-links.index') }}"
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
                        <th class="text-left px-3 py-2">Codigo</th>
                        <th class="text-left px-3 py-2">Valor</th>
                        <th class="text-left px-3 py-2">Source</th>
                        <th class="text-left px-3 py-2">Estado</th>
                        <th class="text-left px-3 py-2">Actualizado</th>
                        <th class="text-left px-3 py-2 w-56">Acciones</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-white/10 text-white/80">
                    @forelse($siteLinks as $siteLink)
                        <tr class="hover:bg-white/5">
                            <td class="px-3 py-2">{{ $siteLink->id }}</td>
                            <td class="px-3 py-2">
                                <span class="px-2 py-1 rounded-lg bg-white/10 border border-white/10 text-xs">{{ $siteLink->code }}</span>
                            </td>
                            <td class="px-3 py-2">{{ $siteLink->name }}</td>
                            <td class="px-3 py-2">{{ optional($siteLink->source)->name ?? 'Sin source' }}</td>
                            <td class="px-3 py-2">
                                <span class="px-2 py-1 rounded-lg text-xs border {{ $siteLink->is_active ? 'bg-emerald-500/10 border-emerald-300/20 text-emerald-200' : 'bg-white/10 border-white/10 text-white/70' }}">
                                    {{ $siteLink->is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-3 py-2">{{ optional($siteLink->updated_at)->format('Y-m-d H:i') }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <a class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs"
                                       href="{{ route('site-links.show', $siteLink) }}">Ver</a>
                                    <a class="px-3 py-1.5 rounded-lg bg-indigo-500/20 hover:bg-indigo-500/30 border border-white/10 text-xs"
                                       href="{{ route('site-links.edit', $siteLink) }}">Editar</a>
                                    <form action="{{ route('site-links.destroy', $siteLink) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button class="px-3 py-1.5 rounded-lg bg-rose-500/20 hover:bg-rose-500/30 border border-rose-300/20 text-xs"
                                                onclick="return confirm('Eliminar site link?')">
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-8 text-center text-white/60">No hay site links registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $siteLinks->links() }}</div>
    </div>
@endsection
