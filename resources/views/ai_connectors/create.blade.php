@extends('meta.layout')

@section('title', 'Nuevo conector IA')
@section('subtitle', 'Crea credenciales independientes para un conector MCP read-only')

@section('content')
    <form method="POST" action="{{ route('ai-connectors.store') }}">
        @csrf
        @include('ai_connectors._form')
    </form>
@endsection
