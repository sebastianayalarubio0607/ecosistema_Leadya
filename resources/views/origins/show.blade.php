@extends('meta.layout')

@section('title', $origin->name)
@section('subtitle', 'Detalle del origen')

@section('header_actions')
    <a href="{{ route('origins.edit', $origin) }}"
       class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">
        Editar
    </a>
    <a href="{{ route('origins.index') }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        Volver
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-6 text-white/80">
        <div class="grid gap-4">
            <div>
                <div class="text-sm text-white/50">ID</div>
                <div class="mt-1">{{ $origin->id }}</div>
            </div>
            <div>
                <div class="text-sm text-white/50">Código</div>
                <div class="mt-1">{{ $origin->code }}</div>
            </div>
            <div>
                <div class="text-sm text-white/50">Nombre</div>
                <div class="mt-1">{{ $origin->name }}</div>
            </div>
            <div>
                <div class="text-sm text-white/50">Estado</div>
                <div class="mt-1">{{ $origin->is_active ? 'Activo' : 'Inactivo' }}</div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <div class="text-sm text-white/50">Creado</div>
                    <div class="mt-1">{{ optional($origin->created_at)->format('Y-m-d H:i') }}</div>
                </div>
                <div>
                    <div class="text-sm text-white/50">Actualizado</div>
                    <div class="mt-1">{{ optional($origin->updated_at)->format('Y-m-d H:i') }}</div>
                </div>
            </div>
            <div class="pt-3">
                <form method="POST" action="{{ route('origins.destroy', $origin) }}" onsubmit="return confirm('¿Seguro que deseas eliminar este origen?');">
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
