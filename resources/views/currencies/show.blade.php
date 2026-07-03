@extends('meta.layout')

@section('title', $currency->code)
@section('subtitle', 'Detalle de la divisa')

@section('header_actions')
    <a href="{{ route('currencies.edit', $currency) }}"
       class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">
        Editar
    </a>
    <a href="{{ route('currencies.index') }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        Volver
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-6 text-white/80">
        <div class="grid gap-4">
            <div>
                <div class="text-sm text-white/50">ID</div>
                <div class="mt-1">{{ $currency->id }}</div>
            </div>
            <div>
                <div class="text-sm text-white/50">Nombre</div>
                <div class="mt-1">{{ $currency->name }}</div>
            </div>
            <div>
                <div class="text-sm text-white/50">Codigo</div>
                <div class="mt-1 font-mono">{{ $currency->code }}</div>
            </div>
            <div>
                <div class="text-sm text-white/50">Estado</div>
                <div class="mt-1">{{ $currency->status ? 'Activa' : 'Inactiva' }}</div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <div class="text-sm text-white/50">Creada</div>
                    <div class="mt-1">{{ optional($currency->created_at)->format('Y-m-d H:i') }}</div>
                </div>
                <div>
                    <div class="text-sm text-white/50">Actualizada</div>
                    <div class="mt-1">{{ optional($currency->updated_at)->format('Y-m-d H:i') }}</div>
                </div>
            </div>
            <div class="pt-3">
                <form method="POST" action="{{ route('currencies.destroy', $currency) }}" onsubmit="return confirm('Seguro que deseas eliminar esta divisa?');">
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
