@extends('meta.layout')

@section('title', 'Editar Integration Type')
@section('subtitle', 'Ajusta el catálogo visualmente con el patrón de geos')

@section('header_actions')
    <a href="{{ route('integrationtypes.index') }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        Volver
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-6">
        <form method="POST" action="{{ route('integrationtypes.update', $type) }}">
            @csrf
            @method('PUT')
            @include('integrationtypes._form', ['type' => $type])
        </form>
    </div>
@endsection
