@extends('meta.layout')

@section('title', 'Detalle Meta Ad Account')

@section('header_actions')
    <a href="{{ route('meta.ad-accounts.edit', $ad_account) }}"
       class="px-4 py-2 rounded-xl bg-indigo-500/20 hover:bg-indigo-500/30 text-white border border-white/10">
        Editar
    </a>
    <a href="{{ route('meta.ad-accounts.status-history.index', ['meta_ad_account_id' => $ad_account->id]) }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        Historial estados
    </a>
    <form method="POST" action="{{ route('meta.ad-accounts.statuses.sync', $ad_account) }}">
        @csrf
        <button class="px-4 py-2 rounded-xl bg-sky-500/20 hover:bg-sky-500/30 text-white border border-white/10">Consultar estado</button>
    </form>
    <a href="{{ route('meta.ad-accounts.index') }}" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">← Volver</a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-3">
        <div class="text-sm text-white/60">
            Cliente: <span class="text-white/85 font-semibold">{{ $ad_account->customer?->name ?? '—' }}</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Meta Account ID</div>
                <div class="text-white font-semibold">{{ $ad_account->meta_account_id }}</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Nombre</div>
                <div class="text-white font-semibold">{{ $ad_account->name }}</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Estado</div>
                <div class="text-white font-semibold">{{ $ad_account->status }}</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Estado Meta</div>
                <div class="text-white font-semibold">{{ $ad_account->estado_meta ?? 'Sin consultar' }}</div>
                <div class="text-white/60 text-sm">{{ $ad_account->estado_meta_nombre ?: 'Sin estado reportado' }}</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Suscripcion Meta</div>
                <div class="mt-1">
                    <span class="inline-flex rounded-lg border px-2 py-1 text-xs font-semibold {{ $ad_account->is_subscribed_to_meta_app ? 'bg-emerald-500/15 border-emerald-300/30 text-emerald-200 shadow-sm shadow-emerald-950/30' : 'bg-rose-500/15 border-rose-300/30 text-rose-200 shadow-sm shadow-rose-950/30' }}">
                        {{ $ad_account->is_subscribed_to_meta_app ? 'Suscrita' : 'No suscrita' }}
                    </span>
                </div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Token puede consultar</div>
                <div class="text-white font-semibold">
                    {{ is_null($ad_account->token_can_view_account) ? 'Sin validar' : ($ad_account->token_can_view_account ? 'Si' : 'No') }}
                </div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="text-white/60 text-xs">Timestamps</div>
                <div class="text-white/80 text-sm">Creado: {{ $ad_account->created_at }}</div>
                <div class="text-white/80 text-sm">Actualizado: {{ $ad_account->updated_at }}</div>
                <div class="text-white/80 text-sm">Revision suscripcion: {{ optional($ad_account->subscription_checked_at)->format('Y-m-d H:i') ?: 'Sin validar' }}</div>
                <div class="text-white/80 text-sm">Revision estado Meta: {{ optional($ad_account->estado_meta_checked_at)->format('Y-m-d H:i') ?: 'Sin consultar' }}</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4 md:col-span-2">
                <div class="text-white/60 text-xs mb-2">subscribed_apps</div>
                <pre class="text-white/80 text-xs whitespace-pre-wrap break-all">{{ $ad_account->subscribed_apps ? json_encode($ad_account->subscribed_apps, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'Sin respuesta registrada' }}</pre>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4 md:col-span-2">
                <div class="text-white/60 text-xs mb-2">Ultimo error de estado Meta</div>
                <div class="text-white/80 text-sm break-all">{{ $ad_account->estado_meta_last_error ?: 'Sin errores registrados' }}</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-4 md:col-span-2">
                <div class="text-white/60 text-xs mb-2">Ultimo error de suscripcion</div>
                <div class="text-white/80 text-sm break-all">{{ $ad_account->subscription_last_error ?: 'Sin errores registrados' }}</div>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-3 mt-4" data-sortable-table-wrap>
        <div class="flex items-center justify-between gap-3">
            <h3 class="font-semibold text-white">Historial reciente de estado Meta</h3>
            <a href="{{ route('meta.ad-accounts.status-history.index', ['meta_ad_account_id' => $ad_account->id]) }}" class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs text-white">Ver todo</a>
        </div>
        <div class="hidden px-3 py-2 text-xs text-white/50" data-sort-status>Ordenando...</div>
        <div class="w-full max-w-full overflow-x-auto rounded-xl border border-white/10">
            <table class="w-full min-w-[900px] text-sm" data-sortable-table>
                <thead class="bg-white/5 text-white/70">
                    <tr>
                        <x-sort-header :index="0" label="Fecha" />
                        <x-sort-header :index="1" label="Anterior" />
                        <x-sort-header :index="2" label="Nuevo" />
                        <x-sort-header :index="3" label="Origen" />
                        <x-sort-header :index="4" label="Cambio" />
                        <th class="text-left px-3 py-2 whitespace-nowrap">Error</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10 text-white/80">
                    @forelse($ad_account->statusHistories as $history)
                        <tr class="hover:bg-white/5">
                            <td class="px-3 py-2">{{ optional($history->consulted_at)->format('Y-m-d H:i') }}</td>
                            <td class="px-3 py-2">{{ $history->estado_meta_anterior ?? '-' }} <span class="text-white/50">{{ $history->estado_meta_anterior_nombre }}</span></td>
                            <td class="px-3 py-2">{{ $history->estado_meta_nuevo ?? '-' }} <span class="text-white/50">{{ $history->estado_meta_nuevo_nombre }}</span></td>
                            <td class="px-3 py-2">{{ $history->query_type ?: '-' }}</td>
                            <td class="px-3 py-2">{{ $history->changed ? 'Si' : 'No' }}</td>
                            <td class="px-3 py-2 break-all">{{ $history->error ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-8 text-center text-white/60">Sin historial registrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
