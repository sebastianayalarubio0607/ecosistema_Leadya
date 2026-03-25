@extends('meta.layout')

@section('title', 'Crear Meta Form')
@section('subtitle', 'Registrar formulario de Meta Lead Ads')

@section('header_actions')
    <a href="{{ route('meta.forms.index') }}" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">Volver</a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
        <form method="POST" action="{{ route('meta.forms.store') }}" class="space-y-4">
            @csrf
            @include('meta.forms._form')

            <div class="flex gap-2">
                <button class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">Guardar</button>
                <a href="{{ route('meta.forms.index') }}" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
