@extends('meta.layout')

@section('title', $qualification->name)
@section('subtitle', 'Detalle de la qualification')

@section('header_actions')
    <a href="{{ route('qualifications.edit', $qualification) }}"
       class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">
        Editar
    </a>
    <a href="{{ route('qualifications.index') }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        Volver
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-6 text-white/80">
        <div class="grid gap-4">
            <div>
                <div class="text-sm text-white/50">ID</div>
                <div class="mt-1">{{ $qualification->id }}</div>
            </div>

            <div>
                <div class="text-sm text-white/50">Nombre</div>
                <div class="mt-1">{{ $qualification->name }}</div>
            </div>

            <div>
                <div class="text-sm text-white/50">Funnel</div>
                <div class="mt-1">
                    @if($qualification->funnel)
                        <a class="underline decoration-white/30 underline-offset-4 hover:text-white" href="{{ route('funnels.show', $qualification->funnel) }}">
                            {{ $qualification->funnel->name }} (ID: {{ $qualification->funnel->id }})
                        </a>
                    @else
                        -
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <div class="text-sm text-white/50">Creado</div>
                    <div class="mt-1">{{ optional($qualification->created_at)->format('Y-m-d H:i') }}</div>
                </div>
                <div>
                    <div class="text-sm text-white/50">Actualizado</div>
                    <div class="mt-1">{{ optional($qualification->updated_at)->format('Y-m-d H:i') }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection
