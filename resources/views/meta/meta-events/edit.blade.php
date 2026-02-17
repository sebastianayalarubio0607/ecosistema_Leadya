@extends('meta.layout')

@section('title', 'Editar Meta Event')
<<<<<<< HEAD
@section('subtitle', 'Actualizar evento de Meta')
=======
@section('subtitle', 'Actualizar evento de conversión')
>>>>>>> 3ac2fef11dafeeab5dedfae1f504ba67206b2bba

@section('header_actions')
    <a href="{{ route('meta.meta-events.show', $item) }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        ← Volver
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
        <form method="POST" action="{{ route('meta.meta-events.update', $item) }}">
            @csrf
            @method('PUT')

            @include('meta.meta-events._form', [
                'item' => $item,
                'submitText' => 'Guardar cambios',
                'cancelUrl' => route('meta.meta-events.show', $item),
            ])
        </form>
    </div>
@endsection
<<<<<<< HEAD
=======

>>>>>>> 3ac2fef11dafeeab5dedfae1f504ba67206b2bba
