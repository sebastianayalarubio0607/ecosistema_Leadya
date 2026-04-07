@extends('meta.layout')

@section('title', 'Crear Integración')
@section('subtitle', 'Configuración visual alineada con el catálogo principal')

@section('header_actions')
    <a href="{{ route('integrations.index') }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        Volver
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-6">
        <form method="POST" action="{{ route('integrations.store') }}">
            @csrf
            @include('integrations._form', ['integration' => $integration, 'customers' => $customers, 'types' => $types])
        </form>
    </div>
@endsection
