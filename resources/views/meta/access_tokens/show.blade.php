@extends('meta.layout')

@section('title', 'Detalle Token Meta')
@section('subtitle', 'Estado del token y trazabilidad de sincronización')

@section('header_actions')
    <a href="{{ route('meta.access-tokens.edit', $accessToken) }}" class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">Editar</a>
    <a href="{{ route('meta.access-tokens.index') }}" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">Volver</a>
@endsection

@section('content')
    <div class="grid gap-4">
        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-6 text-white/80 space-y-4">
            <div><span class="text-white/50">Tipo:</span> {{ $accessToken->token_type ?: '—' }}</div>
            <div><span class="text-white/50">Meta App ID:</span> {{ $accessToken->meta_app_id ?: '—' }}</div>
            <div><span class="text-white/50">Meta App Secret:</span> {{ $accessToken->meta_app_secret ?: '—' }}</div>
            <div><span class="text-white/50">Expira:</span> {{ optional($accessToken->expires_at)->format('Y-m-d H:i') ?: '—' }}</div>
            <div><span class="text-white/50">Último refresh:</span> {{ optional($accessToken->refresh_last_run_at)->format('Y-m-d H:i') ?: '—' }}</div>
            <div><span class="text-white/50">Activo:</span> {{ $accessToken->is_active ? 'Sí' : 'No' }}</div>
            <div>
                <div class="text-white/50 mb-1">Short-lived token</div>
                <div class="rounded-xl border border-white/10 bg-slate-900/60 p-3 break-all text-xs">{{ $accessToken->short_lived_token }}</div>
            </div>
            <div>
                <div class="text-white/50 mb-1">Long-lived token</div>
                <div class="rounded-xl border border-white/10 bg-slate-900/60 p-3 break-all text-xs">{{ $accessToken->long_lived_token ?: '—' }}</div>
            </div>
            <div>
                <div class="text-white/50 mb-1">Último error</div>
                <div class="rounded-xl border border-white/10 bg-slate-900/60 p-3 text-sm">{{ $accessToken->last_error ?: 'Sin errores registrados' }}</div>
            </div>
        </div>

        <div class="flex gap-2">
            <form action="{{ route('meta.access-tokens.refresh', $accessToken) }}" method="POST">
                @csrf
                <button class="px-4 py-2 rounded-xl bg-sky-500/20 hover:bg-sky-500/30 border border-white/10 text-white">Refrescar token</button>
            </form>
            <form action="{{ route('meta.access-tokens.sync-pages', $accessToken) }}" method="POST">
                @csrf
                <button class="px-4 py-2 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 border border-white/10 text-white">Sincronizar páginas</button>
            </form>
            <form action="{{ route('meta.access-tokens.destroy', $accessToken) }}" method="POST">
                @csrf
                @method('DELETE')
                <button class="px-4 py-2 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 border border-rose-300/20 text-white" onclick="return confirm('¿Eliminar token Meta?')">Eliminar</button>
            </form>
        </div>
    </div>
@endsection
