@extends('meta.layout')

@section('title', 'Nuevo Pipeline')
@section('subtitle', 'Registra un Pipeline para cualquier integración')

@section('header_actions')
    <a href="{{ route('pipelines.index') }}" class="rounded-xl border border-white/10 bg-white/10 px-4 py-2 text-white hover:bg-white/15">Volver</a>
@endsection

@section('content')
    <livewire:pipeline-create-form />
@endsection
