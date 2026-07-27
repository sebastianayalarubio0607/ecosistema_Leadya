@extends('meta.layout')

@section('title', 'Editar Site Link')
@section('subtitle', 'Actualiza un site link del generador de URLs')

@section('header_actions')
    <a href="{{ route('site-links.index') }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        Volver
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4">
        <form method="POST" action="{{ route('site-links.update', $siteLink) }}">
            @csrf
            @method('PUT')
            @include('site_links._form', ['siteLink' => $siteLink, 'sources' => $sources])
        </form>
    </div>
@endsection
