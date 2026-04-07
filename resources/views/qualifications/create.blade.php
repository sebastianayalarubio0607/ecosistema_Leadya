@extends('meta.layout')

@section('title', 'Crear Qualification')
@section('subtitle', 'Nueva calificación asociable a funnels')

@section('header_actions')
    <a href="{{ route('qualifications.index') }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        Volver
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-6">
        <form method="POST" action="{{ route('qualifications.store') }}">
            @csrf
            @include('qualifications._form', ['qualification' => $qualification, 'funnels' => $funnels])
        </form>
    </div>
@endsection
