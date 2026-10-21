@extends('meta.layout')

@section('title', 'Pipeline')
@section('subtitle', $pipeline->name)

@section('header_actions')
    <a href="{{ route('pipelines.index') }}" class="rounded-xl border border-white/10 bg-white/10 px-4 py-2 text-white hover:bg-white/15">Volver</a>
    <a href="{{ route('pipelines.edit', $pipeline) }}" class="rounded-xl border border-white/10 bg-indigo-500/30 px-4 py-2 text-white hover:bg-indigo-500/40">Editar</a>
@endsection

@section('content')
    <section class="rounded-2xl border border-white/10 bg-zinc-950/25 p-5 text-white backdrop-blur space-y-5">
        @if(session('success'))<div class="rounded-xl border border-emerald-300/20 bg-emerald-400/10 p-3 text-emerald-100">{{ session('success') }}</div>@endif
        <dl class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-white/10 bg-white/5 p-4"><dt class="text-sm text-white/50">Nombre</dt><dd>{{ $pipeline->name }}</dd></div>
            <div class="rounded-xl border border-white/10 bg-white/5 p-4"><dt class="text-sm text-white/50">ID externo</dt><dd>{{ $pipeline->external_id }}</dd></div>
            <div class="rounded-xl border border-white/10 bg-white/5 p-4"><dt class="text-sm text-white/50">Integración</dt><dd>{{ $pipeline->integration?->name ?? '—' }} / {{ $pipeline->integration?->customer?->name ?? '—' }}</dd></div>
        </dl>
        <div>
            <h2 class="mb-2 font-semibold">CRM States asociados</h2>
            <div class="overflow-x-auto rounded-xl border border-white/10">
                <table class="min-w-full text-left text-sm"><thead class="bg-white/5 text-white/60"><tr><th class="px-3 py-2">ID</th><th class="px-3 py-2">Estado</th><th class="px-3 py-2">Avance</th></tr></thead>
                    <tbody class="divide-y divide-white/10">@forelse($pipeline->crmStates as $state)<tr><td class="px-3 py-2">{{ $state->id }}</td><td class="px-3 py-2">{{ $state->name }}</td><td class="px-3 py-2">{{ $state->progress_percentage !== null ? $state->progress_percentage.'%' : 'Sin configurar' }}</td></tr>@empty<tr><td colspan="3" class="px-3 py-6 text-center text-white/50">No hay CRM States asociados.</td></tr>@endforelse</tbody>
                </table>
            </div>
        </div>
        <form method="POST" action="{{ route('pipelines.destroy', $pipeline) }}" onsubmit="return confirm('¿Eliminar este Pipeline? Los CRM States asociados quedarán sin Pipeline.')" class="flex justify-end">
            @csrf
            @method('DELETE')
            <button class="rounded-xl border border-rose-300/20 px-4 py-2 text-rose-200 hover:bg-rose-400/10">Eliminar Pipeline</button>
        </form>
    </section>
@endsection
