<<<<<<< HEAD
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                Editar CRM State
            </h2>

            <a href="{{ route('crmstates.index') }}"
               class="px-4 py-2 rounded bg-gray-200 dark:bg-gray-200">
                Volver
            </a>
        </div>
    </x-slot>

    <div class="p-6 max-w-6xl mx-auto">
        <div class="bg-white dark:bg-gray-900 rounded shadow p-6">
            <form method="POST" action="{{ route('crmstates.update', $crmstate) }}">
                @csrf
                @method('PUT')
                @include('crm_states._form', [
                    'crmstate' => $crmstate,
                    'integrationId' => $integrationId,
                    'externalId' => $externalId,
                    'qualifications' => $qualifications
                ])
            </form>
        </div>
    </div>
</x-app-layout>
=======
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
>>>>>>> 3ac2fef11dafeeab5dedfae1f504ba67206b2bba
