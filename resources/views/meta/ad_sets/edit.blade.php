@extends('meta.layout')

@section('title', 'Editar Ad Set')

@section('header_actions')
    <a href="{{ route('meta.ad-sets.index') }}" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">← Volver</a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
        <form method="POST" action="{{ route('meta.ad-sets.update', $ad_set) }}" class="space-y-4">
            @csrf @method('PUT')
            @include('meta.ad_sets._form')
            <div class="flex items-center gap-2">
                <button class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">Actualizar</button>
                <a href="{{ route('meta.ad-sets.index') }}" class="px-4 py-2 rounded-xl bg-zinc-950/25 hover:bg-white/10 text-white border border-white/10">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
