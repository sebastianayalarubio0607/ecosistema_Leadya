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
                <div class="text-white/60 text-xs">Suscripcion Meta</div>
                <div class="mt-1">
                    <span class="inline-flex rounded-lg border px-2 py-1 text-xs font-semibold {{ $ad_account->is_subscribed_to_meta_app ? 'bg-emerald-500/15 border-emerald-300/30 text-emerald-200 shadow-sm shadow-emerald-950/30' : 'bg-rose-500/15 border-rose-300/30 text-rose-200 shadow-sm shadow-rose-950/30' }}">
                        {{ $ad_account->is_subscribed_to_meta_app ? 'Suscrita' : 'No suscrita' }}
                    </span>
                </div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Token puede consultar</div>
                <div class="text-white font-semibold">
                    {{ is_null($ad_account->token_can_view_account) ? 'Sin validar' : ($ad_account->token_can_view_account ? 'Si' : 'No') }}
                </div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Timestamps</div>
                <div class="text-white/80 text-sm">Creado: {{ $ad_account->created_at }}</div>
                <div class="text-white/80 text-sm">Actualizado: {{ $ad_account->updated_at }}</div>
                <div class="text-white/80 text-sm">Revision suscripcion: {{ optional($ad_account->subscription_checked_at)->format('Y-m-d H:i') ?: 'Sin validar' }}</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4 md:col-span-2">
                <div class="text-white/60 text-xs mb-2">subscribed_apps</div>
                <pre class="text-white/80 text-xs whitespace-pre-wrap break-all">{{ $ad_account->subscribed_apps ? json_encode($ad_account->subscribed_apps, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'Sin respuesta registrada' }}</pre>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4 md:col-span-2">
                <div class="text-white/60 text-xs mb-2">Ultimo error de suscripcion</div>
                <div class="text-white/80 text-sm break-all">{{ $ad_account->subscription_last_error ?: 'Sin errores registrados' }}</div>
            </div>
        </div>
    </div>
@endsection
