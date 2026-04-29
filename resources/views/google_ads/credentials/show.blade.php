@extends('meta.layout')

@section('title', 'Detalle Credencial Global Google Ads')
@section('subtitle', 'Visualización segura de la única credencial global del sistema')

@section('header_actions')
    <a href="{{ route('google-ads.credentials.edit', $credential) }}"
       class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">
        Editar
    </a>
    <a href="{{ route('google-ads.credentials.index') }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        Volver
    </a>
@endsection

@section('content')
    <div class="grid gap-4">
        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-6 text-white/80 space-y-4">
            <div><span class="text-white/50">Activo:</span> {{ $credential->is_active ? 'Sí' : 'No' }}</div>
            <div><span class="text-white/50">Expira access token:</span> {{ optional($credential->access_token_expires_at)->format('Y-m-d H:i') ?: '—' }}</div>

            @include('google_ads.partials.reveal-secret', ['credential' => $credential, 'field' => 'mcc_developer_token', 'label' => 'Developer Token MCC'])
            @include('google_ads.partials.reveal-secret', ['credential' => $credential, 'field' => 'client_id', 'label' => 'Client ID'])
            @include('google_ads.partials.reveal-secret', ['credential' => $credential, 'field' => 'client_secret', 'label' => 'Client Secret', 'rows' => 3])
            @include('google_ads.partials.reveal-secret', ['credential' => $credential, 'field' => 'refresh_token', 'label' => 'Refresh Token', 'rows' => 3])
            @include('google_ads.partials.reveal-secret', ['credential' => $credential, 'field' => 'access_token', 'label' => 'Access Token', 'rows' => 3])
            @include('google_ads.partials.reveal-secret', ['credential' => $credential, 'field' => 'customer_id', 'label' => 'Customer ID de Google Ads'])
            @include('google_ads.partials.reveal-secret', ['credential' => $credential, 'field' => 'mcc_id', 'label' => 'MCC ID'])
        </div>

        <div class="flex gap-2 flex-wrap">
            <form method="POST" action="{{ route('google-ads.credentials.refresh-token', $credential) }}">
                @csrf
                <button class="px-4 py-2 rounded-xl bg-sky-500/20 hover:bg-sky-500/30 border border-white/10 text-white">
                    Refrescar token
                </button>
            </form>

            <form method="POST" action="{{ route('google-ads.credentials.destroy', $credential) }}">
                @csrf
                @method('DELETE')
                <button class="px-4 py-2 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 border border-rose-300/20 text-white"
                        onclick="return confirm('¿Eliminar credencial global de Google Ads?')">
                    Eliminar
                </button>
            </form>
        </div>
    </div>
@endsection
