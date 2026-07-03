@extends('meta.layout')

@section('title', 'Editar Divisa')
@section('subtitle', 'Actualiza una divisa')

@section('header_actions')
    <a href="{{ route('currencies.index') }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        Volver
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4">
        <form method="POST" action="{{ route('currencies.update', $currency) }}">
            @csrf
            @method('PUT')
            @include('currencies._form', ['currency' => $currency])
        </form>
    </div>
@endsection
