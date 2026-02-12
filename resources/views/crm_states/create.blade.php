@extends('meta.layout')

@section('title', 'Crear CRM State')
@section('subtitle', 'Crear estado de CRM y asignar Meta Event')

@section('header_actions')
    <a href="{{ route('crmstates.index') }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        ← Volver
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
        <form method="POST" action="{{ route('crmstates.store') }}">
            @csrf

            @include('crm_states._form', [
                'crmstate' => $crmstate,
                'integrations' => $integrations,
                'qualifications' => $qualifications,
                'metaEvents' => $metaEvents,
                'isCreate' => true,
                'submitText' => 'Guardar',
                'cancelUrl' => route('crmstates.index'),
            ])
        </form>
    </div>
@endsection
