@extends('meta.layout')

@section('title', $funnel->name)
@section('subtitle', 'Detalle del funnel')

@section('header_actions')
    <a href="{{ route('funnels.edit', $funnel) }}"
       class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">
        Editar
    </a>
    <a href="{{ route('funnels.index') }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        Volver
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-6 text-white/80">
        <div class="grid gap-4">
            <div>
                <div class="text-sm text-white/50">ID</div>
                <div class="mt-1">{{ $funnel->id }}</div>
            </div>

            <div>
                <div class="text-sm text-white/50">Nombre</div>
                <div class="mt-1">{{ $funnel->name }}</div>
            </div>

            <div>
                <div class="text-sm text-white/50">DescripciÃ³n</div>
                <div class="mt-1">{{ $funnel->description ?: '-' }}</div>
            </div>

            <div>
                <div class="text-sm text-white/50">Estado</div>
                <div class="mt-1">
                    <span class="px-2 py-1 rounded-lg text-xs border {{ $funnel->status === 'active' ? 'bg-emerald-500/10 border-emerald-300/20 text-emerald-200' : 'bg-white/10 border-white/10 text-white/70' }}">
                        {{ $funnel->status }}
                    </span>
                </div>
            </div>

            <div>
                <div class="text-sm text-white/50">Meta Event</div>
                <div class="mt-1">
                    @if($funnel->meta_event_id)
                        @php
                            $metaLabel = optional($funnel->metaEvent)->name
                                ?? optional($funnel->metaEvent)->event_name
                                ?? optional($funnel->metaEvent)->title
                                ?? null;
                        @endphp

                        <span class="px-2 py-1 rounded-lg border border-white/10 bg-white/10 text-xs">
                            {{ $metaLabel ?: ('ID: ' . $funnel->meta_event_id) }}
                        </span>
                    @else
                        <span class="text-white/50">â€”</span>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <div class="text-sm text-white/50">Creado</div>
                    <div class="mt-1">{{ optional($funnel->created_at)->format('Y-m-d H:i') }}</div>
                </div>
                <div>
                    <div class="text-sm text-white/50">Actualizado</div>
                    <div class="mt-1">{{ optional($funnel->updated_at)->format('Y-m-d H:i') }}</div>
                </div>
            </div>

            <div class="pt-2">
                <div class="mb-2 text-sm text-white/50">Qualifications asociadas</div>

                @if($funnel->qualifications->isEmpty())
                    <div class="text-sm text-white/60">Sin qualifications.</div>
                @else
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach($funnel->qualifications as $qualification)
                            <li class="text-sm">
                                <a class="underline decoration-white/30 underline-offset-4 hover:text-white" href="{{ route('qualifications.show', $qualification) }}">
                                    {{ $qualification->name }} (ID: {{ $qualification->id }})
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="pt-3 flex gap-2">
                <form method="POST"
                      action="{{ route('funnels.destroy', $funnel) }}"
                      onsubmit="return confirm('Â¿Seguro que deseas eliminar este funnel?');">
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
