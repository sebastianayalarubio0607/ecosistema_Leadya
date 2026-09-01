@extends('meta.layout')

@section('title', 'Detalle Meta WhatsApp')

@section('header_actions')
    <a href="{{ route('meta.whatsapps.edit', $whatsapp) }}"
       class="px-4 py-2 rounded-xl bg-indigo-500/20 hover:bg-indigo-500/30 text-white border border-white/10">
        Editar
    </a>
    <a href="{{ route('meta.whatsapps.index') }}" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">Volver</a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-3">
        <div class="text-sm text-white/60">
            Customers:
            <span class="text-white/85 font-semibold">{{ $whatsapp->customers->pluck('name')->join(', ') ?: '-' }}</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="rounded-xl border border-white/10 bg-white/5 p-4 md:col-span-2">
                <div class="text-white/60 text-xs">Credencial WhatsApp explicita</div>
                <div class="text-white font-semibold break-all">
                    @if($whatsapp->metaAccessToken)
                        #{{ $whatsapp->metaAccessToken->id }} {{ $whatsapp->metaAccessToken->name ?: 'System user WhatsApp' }} / app {{ $whatsapp->metaAccessToken->meta_app_id ?: '-' }}
                    @else
                        Resolver por customer o default WhatsApp
                    @endif
                </div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">WABA ID</div>
                <div class="text-white font-semibold break-all">{{ $whatsapp->waba_id }}</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Phone Number ID</div>
                <div class="text-white font-semibold break-all">{{ $whatsapp->phone_number_id ?: '-' }}</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">WA ID</div>
                <div class="text-white font-semibold break-all">{{ $whatsapp->wa_id ?: '-' }}</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Estado</div>
                <div class="text-white font-semibold">{{ $whatsapp->status ? 'Activo' : 'Inactivo' }}</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Suscripcion Meta</div>
                <div class="mt-1">
                    <span class="inline-flex rounded-lg border px-2 py-1 text-xs font-semibold {{ $whatsapp->is_subscribed_to_meta_app ? 'bg-emerald-500/15 border-emerald-300/30 text-emerald-200 shadow-sm shadow-emerald-950/30' : 'bg-rose-500/15 border-rose-300/30 text-rose-200 shadow-sm shadow-rose-950/30' }}">
                        {{ $whatsapp->is_subscribed_to_meta_app ? 'Suscrita' : 'No suscrita' }}
                    </span>
                </div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Token puede consultar</div>
                <div class="text-white font-semibold">
                    {{ is_null($whatsapp->token_can_view_account) ? 'Sin validar' : ($whatsapp->token_can_view_account ? 'Si' : 'No') }}
                </div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Token usado en ultima validacion</div>
                <div class="text-white font-semibold break-all">
                    @if($whatsapp->subscriptionMetaAccessToken)
                        #{{ $whatsapp->subscriptionMetaAccessToken->id }} {{ $whatsapp->subscriptionMetaAccessToken->name ?: 'System user WhatsApp' }}
                    @else
                        -
                    @endif
                </div>
                <div class="text-white/50 text-xs">{{ $whatsapp->subscription_token_source ?: '-' }}</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Meta App validada</div>
                <div class="text-white font-semibold break-all">{{ $whatsapp->subscription_meta_app_id ?: '-' }}</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4 md:col-span-2">
                <div class="text-white/60 text-xs mb-2">subscribed_apps</div>
                <pre class="text-white/80 text-xs whitespace-pre-wrap break-all">{{ $whatsapp->subscribed_apps ? json_encode($whatsapp->subscribed_apps, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'Sin respuesta registrada' }}</pre>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4 md:col-span-2">
                <div class="text-white/60 text-xs mb-2">Ultimo error de suscripcion</div>
                <div class="text-white/80 text-sm break-all">{{ $whatsapp->subscription_last_error ?: 'Sin errores registrados' }}</div>
            </div>
        </div>
    </div>
@endsection
