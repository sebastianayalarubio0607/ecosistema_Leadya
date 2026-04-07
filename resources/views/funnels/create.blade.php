@extends('meta.layout')

@section('title', 'Crear Funnel')
@section('subtitle', 'Asocia el funnel con sus qualifications y evento Meta')

@section('header_actions')
    <a href="{{ route('funnels.index') }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        Volver
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-6">
        <form method="POST" action="{{ route('funnels.store') }}">
            @csrf

            @include('funnels._form', [
                'funnel' => $funnel,
                'qualifications' => $qualifications,
                'selectedQualificationIds' => $selectedQualificationIds,
            ])
        </form>
    </div>
@endsection
