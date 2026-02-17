@extends('meta.layout')

@section('title', 'Crear Meta Event')
@section('subtitle', 'Nuevo evento de Meta')

@section('header_actions')
    <a href="{{ route('meta.meta-events.index') }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        ← Volver
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
        <form method="POST" action="{{ route('meta.meta-events.store') }}">
            @csrf

            @include('meta.meta-events._form', [
                'item' => null,
                'submitText' => 'Guardar',
                'cancelUrl' => route('meta.meta-events.index'),
            ])
        </form>
    </div>
@endsection
