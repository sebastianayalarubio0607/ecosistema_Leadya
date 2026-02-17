@extends('meta.layout')

@section('title', 'Ver Meta Event')
@section('subtitle', 'Detalle del evento y CRM States asociados')

@section('header_actions')
    <div class="flex gap-2">
        <a href="{{ route('meta.meta-events.index') }}"
           class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
            ← Volver
        </a>
        <a href="{{ route('meta.meta-events.edit', $item) }}"
           class="px-4 py-2 rounded-xl bg-indigo-500/20 hover:bg-indigo-500/30 text-white border border-white/10">
            Editar
        </a>
    </div>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                <div class="text-xs text-white/50">ID</div>
                <div class="text-white">{{ $item->id }}</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                <div class="text-xs text-white/50">Nombre</div>
                <div class="text-white">{{ $item->nombre }}</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                <div class="text-xs text-white/50">Estado</div>
                <div class="text-white">{{ $item->estados }}</div>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-white/10">
            <table class="min-w-full text-sm">
                <thead class="bg-white/5 text-white/70">
                    <tr>
                        <th class="text-left px-3 py-2">CRM State ID</th>
                        <th class="text-left px-3 py-2">Nombre</th>
                        <th class="text-left px-3 py-2">Qualification</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-white/10 text-white/80">
                    @forelse($item->crmStates as $s)
                        <tr class="hover:bg-white/5">
                            <td class="px-3 py-2">{{ $s->id }}</td>
                            <td class="px-3 py-2">{{ $s->name }}</td>
                            <td class="px-3 py-2">{{ $s->qualificationModel?->name ?? $s->qualification ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-3 py-8 text-center text-white/60">
                                No hay CRM States asociados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <form action="{{ route('meta.meta-events.destroy', $item) }}" method="POST">
            @csrf @method('DELETE')
            <button class="px-4 py-2 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 border border-rose-300/20 text-white"
                    onclick="return confirm('¿Eliminar Meta Event?')">
                Eliminar
            </button>
        </form>
    </div>
@endsection
