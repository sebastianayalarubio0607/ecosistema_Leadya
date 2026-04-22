@extends('meta.layout')

@section('title', $campaignObjective->nombre)
@section('subtitle', 'Detalle del objetivo de campaña')

@section('header_actions')
    <a href="{{ route('campaign_objectives.edit', $campaignObjective) }}"
       class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">
        Editar
    </a>
    <a href="{{ route('campaign_objectives.index') }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        Volver
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-6 text-white/80">
        <div class="grid gap-4">
            <div>
                <div class="text-sm text-white/50">ID</div>
                <div class="mt-1">{{ $campaignObjective->id }}</div>
            </div>
            <div>
                <div class="text-sm text-white/50">Nombre</div>
                <div class="mt-1">{{ $campaignObjective->nombre }}</div>
            </div>
            <div>
                <div class="text-sm text-white/50">Estado</div>
                <div class="mt-1">{{ $campaignObjective->estado ? 'Activo' : 'Inactivo' }}</div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <div class="text-sm text-white/50">Creado</div>
                    <div class="mt-1">{{ optional($campaignObjective->created_at)->format('Y-m-d H:i') }}</div>
                </div>
                <div>
                    <div class="text-sm text-white/50">Actualizado</div>
                    <div class="mt-1">{{ optional($campaignObjective->updated_at)->format('Y-m-d H:i') }}</div>
                </div>
            </div>
            <div class="pt-3">
                <form method="POST" action="{{ route('campaign_objectives.destroy', $campaignObjective) }}" onsubmit="return confirm('¿Seguro que deseas eliminar este objetivo de campaña?');">
                    @csrf
                    @method('DELETE')
                    <button class="px-4 py-2 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 border border-rose-300/20 text-white" type="submit">
                        Eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
