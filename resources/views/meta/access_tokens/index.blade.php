@extends('meta.layout')

@section('title', 'Meta Access Tokens')
@section('subtitle', 'Gestión global de tokens Meta por tipo')

@section('header_actions')
    <a href="{{ route('meta.access-tokens.create') }}" class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">
        + Nuevo
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <div class="md:col-span-6">
                <label class="block mb-1 text-white/70">Buscar</label>
                <input name="search" value="{{ request('search') }}"
                       class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
                       placeholder="Buscar por tipo, app id o error">
            </div>

            <div class="md:col-span-4">
                <label class="block mb-1 text-white/70">Tipo</label>
                <select name="token_type" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">-- Todos --</option>
                    @foreach($tokenTypes as $tokenType)
                        <option value="{{ $tokenType }}" @selected(request('token_type') === $tokenType)>
                            {{ $tokenType }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-2 flex gap-2">
                <button class="w-full px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">Filtrar</button>
                <a href="{{ route('meta.access-tokens.index') }}" class="w-full text-center px-4 py-2 rounded-xl bg-zinc-950/25 hover:bg-white/10 text-white border border-white/10">Limpiar</a>
            </div>
        </form>

        <div class="overflow-x-auto rounded-xl border border-white/10">
            <table class="min-w-full text-sm">
                <thead class="bg-white/5 text-white/70">
                    <tr>
                        <th class="text-left px-3 py-2">Tipo</th>
                        <th class="text-left px-3 py-2">Meta App ID</th>
                        <th class="text-left px-3 py-2">Expira</th>
                        <th class="text-left px-3 py-2">Activo</th>
                        <th class="text-left px-3 py-2">Último refresh</th>
                        <th class="text-left px-3 py-2 w-72">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10 text-white/80">
                    @forelse($items as $item)
                        <tr class="hover:bg-white/5">
                            <td class="px-3 py-2">{{ $item->token_type ?: '—' }}</td>
                            <td class="px-3 py-2">{{ $item->meta_app_id ?: '—' }}</td>
                            <td class="px-3 py-2">{{ optional($item->expires_at)->format('Y-m-d H:i') ?: '—' }}</td>
                            <td class="px-3 py-2">
                                <span class="px-2 py-1 rounded-lg text-xs border {{ $item->is_active ? 'bg-emerald-500/10 border-emerald-300/20 text-emerald-200' : 'bg-white/10 border-white/10 text-white/70' }}">
                                    {{ $item->is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-3 py-2">{{ optional($item->refresh_last_run_at)->format('Y-m-d H:i') ?: '—' }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <a class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs" href="{{ route('meta.access-tokens.show', $item) }}">Ver</a>
                                    <a class="px-3 py-1.5 rounded-lg bg-indigo-500/20 hover:bg-indigo-500/30 border border-white/10 text-xs" href="{{ route('meta.access-tokens.edit', $item) }}">Editar</a>
                                    <form action="{{ route('meta.access-tokens.refresh', $item) }}" method="POST">
                                        @csrf
                                        <button class="px-3 py-1.5 rounded-lg bg-sky-500/20 hover:bg-sky-500/30 border border-white/10 text-xs">Refresh</button>
                                    </form>
                                    <form action="{{ route('meta.access-tokens.sync-pages', $item) }}" method="POST">
                                        @csrf
                                        <button class="px-3 py-1.5 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 border border-white/10 text-xs">Sync Pages</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-8 text-center text-white/60">No hay tokens Meta registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $items->links() }}</div>
    </div>
@endsection
