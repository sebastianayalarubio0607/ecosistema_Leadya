@extends('meta.layout')

@section('title', 'Detalle Ad Set')

@section('header_actions')
    <a href="{{ route('meta.ad-sets.edit', $ad_set) }}"
       class="px-4 py-2 rounded-xl bg-indigo-500/20 hover:bg-indigo-500/30 text-white border border-white/10">
        Editar
    </a>
    <a href="{{ route('meta.ad-sets.index') }}" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">← Volver</a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-3">
        <div class="text-sm text-white/60">
            Customer: <span class="text-white/85 font-semibold">{{ $ad_set->campaign?->account?->customer?->name ?? '—' }}</span>
        </div>
        <div class="text-sm text-white/60">
            Campaign:
            <span class="text-white/85 font-semibold">{{ $ad_set->campaign?->name ?? '—' }}</span>
            <span class="text-white/40">({{ $ad_set->campaign?->meta_campaign_id }})</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Meta Ad Set ID</div>
                <div class="text-white font-semibold">{{ $ad_set->meta_ad_set_id }}</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Name</div>
                <div class="text-white font-semibold">{{ $ad_set->name }}</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Optimization Goal</div>
                <div class="text-white font-semibold">{{ $ad_set->optimization_goal }}</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Attribution Setting</div>
                <div class="text-white font-semibold">{{ $ad_set->attribution_setting }}</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4 md:col-span-2">
                <div class="text-white/60 text-xs">Status</div>
                <div class="text-white font-semibold">{{ $ad_set->status }}</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4 md:col-span-2">
                <div class="text-white/60 text-xs">Timestamps</div>
                <div class="text-white/80 text-sm">Creado: {{ $ad_set->created_at }}</div>
                <div class="text-white/80 text-sm">Actualizado: {{ $ad_set->updated_at }}</div>
            </div>
        </div>
    </div>
@endsection
