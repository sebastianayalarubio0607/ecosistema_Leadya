@extends('meta.layout')

@section('title', 'Detalle Insight')

@section('header_actions')
    <a href="{{ route('meta.insights.edit', $insight) }}"
       class="px-4 py-2 rounded-xl bg-indigo-500/20 hover:bg-indigo-500/30 text-white border border-white/10">
        Editar
    </a>
    <a href="{{ route('meta.insights.index') }}" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">← Volver</a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Fecha</div>
                <div class="text-white font-semibold">
                    {{ $insight->date_start?->format('Y-m-d') }} → {{ $insight->date_stop?->format('Y-m-d') }}
                </div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Cuenta</div>
                <div class="text-white font-semibold">{{ $insight->account_name }}</div>
                <div class="text-xs text-white/50">{{ $insight->account_id }}</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Campaña</div>
                <div class="text-white font-semibold">{{ $insight->campaign_name }}</div>
                <div class="text-xs text-white/50">{{ $insight->campaign_id }}</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Ad Set</div>
                <div class="text-white font-semibold">{{ $insight->adset_name }}</div>
                <div class="text-xs text-white/50">{{ $insight->adset_id }}</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4 lg:col-span-2">
                <div class="text-white/60 text-xs">Ad</div>
                <div class="text-white font-semibold">{{ $insight->ad_name }}</div>
                <div class="text-xs text-white/50">{{ $insight->ad_id }}</div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Spend</div>
                <div class="text-white font-semibold">{{ $insight->spend }}</div>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Impressions</div>
                <div class="text-white font-semibold">{{ $insight->impressions }}</div>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Reach</div>
                <div class="text-white font-semibold">{{ $insight->reach }}</div>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Clicks</div>
                <div class="text-white font-semibold">{{ $insight->clicks }}</div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Frequency</div>
                <div class="text-white font-semibold">{{ $insight->frequency }}</div>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">CPM</div>
                <div class="text-white font-semibold">{{ $insight->cpm }}</div>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Unique Clicks</div>
                <div class="text-white font-semibold">{{ $insight->unique_clicks }}</div>
            </div>
        </div>

        <div class="rounded-xl border border-white/10 bg-white/5 p-4">
            <div class="text-white/60 text-xs mb-2">Actions (JSON)</div>
            <pre class="text-xs text-white/80 overflow-auto">{{ json_encode($insight->actions, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
        </div>

        <div class="rounded-xl border border-white/10 bg-white/5 p-4">
            <div class="text-white/60 text-xs mb-1">Status</div>
            <div class="text-white font-semibold">{{ $insight->status }}</div>
            <div class="text-xs text-white/50 mt-2">Creado: {{ $insight->created_at }}</div>
            <div class="text-xs text-white/50">Actualizado: {{ $insight->updated_at }}</div>
        </div>
    </div>
@endsection
