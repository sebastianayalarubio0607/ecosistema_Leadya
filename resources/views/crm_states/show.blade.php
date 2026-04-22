@extends('meta.layout')

@section('title', 'Ver CRM State')
@section('subtitle', 'Detalle del estado, qualification y conversión (Meta Event)')

@section('header_actions')
    <div class="flex gap-2">
        <a href="{{ route('crmstates.index') }}"
           class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
            ← Volver
        </a>
        <a href="{{ route('crmstates.edit', $crmstate) }}"
           class="px-4 py-2 rounded-xl bg-indigo-500/20 hover:bg-indigo-500/30 text-white border border-white/10">
            Editar
        </a>
    </div>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                <div class="text-xs text-white/50">CRM State ID</div>
                <div class="text-white">{{ $crmstate->id }}</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                <div class="text-xs text-white/50">Nombre</div>
                <div class="text-white">{{ $crmstate->name }}</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                <div class="text-xs text-white/50">Qualification</div>
                <div class="text-white">{{ $crmstate->qualificationModel?->name ?? '—' }}</div>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                <div class="text-xs text-white/50">Sin gestionar</div>
                <div class="text-white">{{ $crmstate->unmanaged ? 'Sí' : 'No' }}</div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                <div class="text-xs text-white/50">Integration ID</div>
                <div class="text-white">{{ $integrationId }}</div>
                <div class="text-xs text-white/50 mt-2">Customer / Integration</div>
                <div class="text-white">
                    {{ $integration?->customer?->name ?? '—' }} — {{ $integration?->name ?? '—' }}
                </div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                <div class="text-xs text-white/50">External ID</div>
                <div class="text-white">{{ $externalId }}</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                <div class="text-xs text-white/50">Meta Event (Conversión)</div>
                <div class="text-white">{{ $crmstate->metaEvent?->nombre ?? '—' }}</div>
                <div class="text-xs text-white/50 mt-2">Estado Meta Event</div>
                <div class="text-white">{{ $crmstate->metaEvent?->estados ?? '—' }}</div>
            </div>
        </div>

        <form action="{{ route('crmstates.destroy', $crmstate) }}" method="POST">
            @csrf @method('DELETE')
            <button class="px-4 py-2 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 border border-rose-300/20 text-white"
                    onclick="return confirm('¿Eliminar CRM State?')">
                Eliminar
            </button>
        </form>
    </div>
@endsection
