@extends('meta.layout')

@section('title', 'Pipelines')
@section('subtitle', 'Pipelines asociados a integraciones y CRM States')

@section('header_actions')
    <a href="{{ route('pipelines.create') }}" class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">+ Nuevo</a>
@endsection

@section('content')
    <livewire:pipeline-manager />
@endsection
