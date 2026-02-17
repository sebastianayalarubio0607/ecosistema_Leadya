@extends('meta.layout')

@section('title', 'Editar CRM State')
@section('subtitle', 'Actualizar estado y conversión (Meta Event)')

@section('header_actions')
    <a href="{{ route('crmstates.show', $crmstate) }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        ← Volver
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
        <form method="POST" action="{{ route('crmstates.update', $crmstate) }}">
            @csrf
            @method('PUT')

            @include('crm_states._form', [
                'crmstate' => $crmstate,
                'integrationId' => $integrationId,
                'externalId' => $externalId,
                'integration' => $integration,
                'qualifications' => $qualifications,
                'metaEvents' => $metaEvents,
                'isCreate' => false,
                'submitText' => 'Guardar cambios',
                'cancelUrl' => route('crmstates.show', $crmstate),
            ])
        </form>
    </div>
@endsection
