@extends('meta.layout')

@section('title', $type->name)
@section('subtitle', 'Detalle del tipo de integración')

@section('header_actions')
    <a href="{{ route('integrationtypes.edit', $type) }}"
       class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">
        Editar
    </a>
    <a href="{{ route('integrationtypes.index') }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        Volver
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-6 text-white/80">
        <div class="grid gap-4">
            <div>
                <div class="text-sm text-white/50">Status</div>
                <div class="mt-1">
                    <span class="px-2 py-1 rounded-lg text-xs border {{ (int) $type->status === 1 ? 'bg-emerald-500/10 border-emerald-300/20 text-emerald-200' : 'bg-white/10 border-white/10 text-white/70' }}">
                        {{ (int) $type->status === 1 ? 'Activo' : 'Inactivo' }}
                    </span>
                </div>
            </div>

            <div>
                <div class="text-sm text-white/50">DescripciÃ³n</div>
                <div class="mt-1">{{ $type->description ?: 'â€”' }}</div>
            </div>

            <div class="pt-2 flex gap-2">
                <form method="POST"
                      action="{{ route('integrationtypes.destroy', $type) }}"
                      onsubmit="return confirm('Â¿Seguro que deseas eliminar este Integration Type?');">
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
