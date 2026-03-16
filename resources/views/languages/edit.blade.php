@extends('meta.layout')

@section('title', 'Editar Language')
@section('subtitle', 'Actualiza un idioma del generador de URLs')

@section('header_actions')
    <a href="{{ route('languages.index') }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        Volver
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4">
        <form method="POST" action="{{ route('languages.update', $language) }}">
            @csrf
            @method('PUT')
            @include('languages._form', ['language' => $language])
        </form>
    </div>
@endsection
