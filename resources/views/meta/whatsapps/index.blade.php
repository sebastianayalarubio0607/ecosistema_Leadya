@extends('meta.layout')

@section('title', 'Meta WhatsApp')
@section('subtitle', 'Cuentas WhatsApp Business, suscripciones webhook y mensajes')

@section('header_actions')
    <a href="{{ route('meta.index') }}" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">Volver</a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-6 text-white/80">
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-sm text-white/50">Vista</div>
                <div class="mt-1 text-lg font-semibold text-white">Meta WhatsApp</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-sm text-white/50">Webhook</div>
                <div class="mt-1 font-mono text-sm break-all text-white">/api/webhooks/meta/whatsapp</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-sm text-white/50">Campo Meta</div>
                <div class="mt-1 text-white font-semibold">messages</div>
            </div>
        </div>
    </div>
@endsection
