@extends('meta.layout')

@section('title', 'Editar conector IA')
@section('subtitle', $connector->name)

@section('header_actions')
    <a href="{{ route('ai-connectors.show', $connector) }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        Ver detalle
    </a>
@endsection

@section('content')
    <form method="POST" action="{{ route('ai-connectors.update', $connector) }}">
        @csrf
        @method('PUT')
        @include('ai_connectors._form')
    </form>
@endsection
