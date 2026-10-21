@extends('meta.layout')

@section('title', 'Editar Pipeline')
@section('subtitle', $pipeline->name)

@section('header_actions')
    <a href="{{ route('pipelines.show', $pipeline) }}" class="rounded-xl border border-white/10 bg-white/10 px-4 py-2 text-white hover:bg-white/15">Volver</a>
@endsection

@section('content')
    <form method="POST" action="{{ route('pipelines.update', $pipeline) }}" class="rounded-2xl border border-white/10 bg-zinc-950/25 p-5 text-white backdrop-blur space-y-4">
        @csrf
        @method('PUT')
        @include('pipelines._form')
        <div class="flex justify-end gap-2"><a href="{{ route('pipelines.show', $pipeline) }}" class="rounded-xl border border-white/10 px-4 py-2 hover:bg-white/5">Cancelar</a><button class="rounded-xl border border-white/10 bg-indigo-500/30 px-4 py-2 hover:bg-indigo-500/40">Guardar cambios</button></div>
    </form>
@endsection
