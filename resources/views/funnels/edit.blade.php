@extends('meta.layout')

@section('title', 'Editar Funnel')
@section('subtitle', 'Ajusta el funnel y sus asociaciones manteniendo el flujo actual')

@section('header_actions')
    <a href="{{ route('funnels.index') }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        Volver
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-6">
        <form method="POST" action="{{ route('funnels.update', $funnel) }}">
            @csrf
            @method('PUT')

            @include('funnels._form', [
                'funnel' => $funnel,
                'qualifications' => $qualifications,
                'selectedQualificationIds' => $selectedQualificationIds,
            ])
        </form>
    </div>
@endsection
