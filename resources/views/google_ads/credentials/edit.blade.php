@extends('meta.layout')

@section('title', 'Editar Credencial Global Google Ads')
@section('subtitle', 'Actualiza la credencial global sin sobrescribir secretos no modificados')

@section('header_actions')
    <a href="{{ route('google-ads.credentials.show', $credential) }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        Volver
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-6">
        <form method="POST" action="{{ route('google-ads.credentials.update', $credential) }}" class="space-y-4">
            @csrf
            @method('PUT')
            @include('google_ads.credentials._form')

            <div class="flex gap-2">
                <button class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">Actualizar</button>
                <a href="{{ route('google-ads.credentials.show', $credential) }}" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
