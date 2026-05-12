@extends('meta.layout')

@section('title', 'Crear Source')
@section('subtitle', 'Nuevo source para origenes')

@section('header_actions')
    <a href="{{ route('sources.index') }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        Volver
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4">
        <form method="POST" action="{{ route('sources.store') }}">
            @csrf
            @include('sources._form', ['source' => $source])
        </form>
    </div>
@endsection
