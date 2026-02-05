@extends('meta.layout')

@section('title', 'Detalle Meta Ad Account')

@section('header_actions')
    <a href="{{ route('meta.ad-accounts.edit', $ad_account) }}"
       class="px-4 py-2 rounded-xl bg-indigo-500/20 hover:bg-indigo-500/30 text-white border border-white/10">
        Editar
    </a>
    <a href="{{ route('meta.ad-accounts.index') }}" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">← Volver</a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-3">
        <div class="text-sm text-white/60">
            Cliente: <span class="text-white/85 font-semibold">{{ $ad_account->customer?->name ?? '—' }}</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Meta Account ID</div>
                <div class="text-white font-semibold">{{ $ad_account->meta_account_id }}</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Nombre</div>
                <div class="text-white font-semibold">{{ $ad_account->name }}</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Estado</div>
                <div class="text-white font-semibold">{{ $ad_account->status }}</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Timestamps</div>
                <div class="text-white/80 text-sm">Creado: {{ $ad_account->created_at }}</div>
                <div class="text-white/80 text-sm">Actualizado: {{ $ad_account->updated_at }}</div>
            </div>
        </div>
    </div>
@endsection
