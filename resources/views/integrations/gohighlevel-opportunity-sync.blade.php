@extends('meta.layout')

@section('title', 'Consulta GoHighLevel')
@section('subtitle', 'Sincronización de oportunidades y conversiones')

@section('content')
    <div class="mx-auto max-w-7xl p-4 sm:p-6">
        <livewire:gohighlevel-opportunity-sync-panel />
    </div>
@endsection
