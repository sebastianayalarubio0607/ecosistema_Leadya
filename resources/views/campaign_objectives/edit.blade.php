@extends('meta.layout')

@section('title', 'Editar Campaign Objective')
@section('subtitle', 'Actualiza un objetivo de campaña')

@section('header_actions')
    <a href="{{ route('campaign_objectives.index') }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        Volver
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4">
        <form method="POST" action="{{ route('campaign_objectives.update', $campaignObjective) }}">
            @csrf
            @method('PUT')
            @include('campaign_objectives._form', ['campaignObjective' => $campaignObjective])
        </form>
    </div>
@endsection
