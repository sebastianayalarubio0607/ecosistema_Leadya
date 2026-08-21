@extends('meta.layout')

@section('title', 'Jobs Suscripcion Meta WhatsApp')
@section('subtitle', 'Cola y fallos de suscripcion de cuentas WhatsApp Business')

@section('header_actions')
    <form method="POST" action="{{ route('meta.whatsapps.subscription-jobs.scan') }}">
        @csrf
        <button class="px-4 py-2 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 text-white border border-white/10">Revisar WhatsApp</button>
    </form>
    <a href="{{ route('meta.whatsapps.index') }}" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">Volver</a>
@endsection

@section('content')
    <div class="grid gap-4">
        <div class="flex gap-2 text-sm">
            <a href="#queued" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">Jobs en cola</a>
            <a href="#failed" class="px-4 py-2 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 text-white border border-rose-300/20">Jobs fallidos</a>
        </div>

        <section id="queued" class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h3 class="font-semibold text-white">Jobs en cola</h3>
                <form method="POST" action="{{ route('meta.whatsapps.subscription-jobs.queued.release-all') }}">
                    @csrf
                    <button class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs text-white">Procesar todo</button>
                </form>
            </div>

            <div class="w-full max-w-full overflow-x-auto rounded-xl border border-white/10 [scrollbar-gutter:stable]" data-sortable-table-wrap>
                <div class="hidden px-3 py-2 text-xs text-white/50" data-sort-status>Ordenando...</div>
                <table class="w-full min-w-[850px] text-sm" data-sortable-table>
                    <thead class="bg-white/5 text-white/70">
                        <tr>
                            <x-sort-header :index="0" label="ID" />
                            <x-sort-header :index="1" label="Job" />
                            <x-sort-header :index="2" label="Intentos" align="right" />
                            <x-sort-header :index="3" label="Disponible" />
                            <x-sort-header :index="4" label="Creado" />
                            <x-sort-header :index="5" label="Acciones" class="w-40" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10 text-white/80">
                        @forelse($queuedJobs as $job)
                            <tr class="hover:bg-white/5">
                                <td class="px-3 py-2">{{ $job->id }}</td>
                                <td class="px-3 py-2">{{ $job->display_name }}</td>
                                <td class="px-3 py-2">{{ $job->attempts }}</td>
                                <td class="px-3 py-2">{{ $job->available_at_label }}</td>
                                <td class="px-3 py-2">{{ $job->created_at_label }}</td>
                                <td class="px-3 py-2">
                                    <form method="POST" action="{{ route('meta.whatsapps.subscription-jobs.queued.release', $job->id) }}">
                                        @csrf
                                        <button class="px-3 py-1.5 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 border border-white/10 text-xs text-white">Procesar</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-8 text-center text-white/60">No hay jobs en cola.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $queuedJobs->links() }}</div>
        </section>

        <section id="failed" class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h3 class="font-semibold text-white">Jobs fallidos</h3>
                <form method="POST" action="{{ route('meta.whatsapps.subscription-jobs.failed.retry-all') }}">
                    @csrf
                    <button class="px-3 py-1.5 rounded-lg bg-rose-500/20 hover:bg-rose-500/30 border border-rose-300/20 text-xs text-white">Reprocesar todo</button>
                </form>
            </div>

            <div class="w-full max-w-full overflow-x-auto rounded-xl border border-white/10 [scrollbar-gutter:stable]" data-sortable-table-wrap>
                <div class="hidden px-3 py-2 text-xs text-white/50" data-sort-status>Ordenando...</div>
                <table class="w-full min-w-[1150px] text-sm" data-sortable-table>
                    <thead class="bg-white/5 text-white/70">
                        <tr>
                            <x-sort-header :index="0" label="ID" />
                            <x-sort-header :index="1" label="Accion" />
                            <x-sort-header :index="2" label="WABA" />
                            <x-sort-header :index="3" label="Fallo" />
                            <x-sort-header :index="4" label="Reintentado" />
                            <x-sort-header :index="5" label="Acciones" class="w-40" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10 text-white/80">
                        @forelse($failedJobs as $failed)
                            <tr class="hover:bg-white/5 align-top">
                                <td class="px-3 py-2">{{ $failed->id }}</td>
                                <td class="px-3 py-2">{{ $failed->action }}</td>
                                <td class="px-3 py-2 break-all">{{ $failed->resource_identifier ?: $failed->resource_id ?: '-' }}</td>
                                <td class="px-3 py-2 max-w-xl">
                                    <div class="line-clamp-3">{{ $failed->exception }}</div>
                                    <div class="mt-1 text-xs text-white/50">{{ optional($failed->failed_at)->format('Y-m-d H:i') }}</div>
                                </td>
                                <td class="px-3 py-2">{{ optional($failed->retried_at)->format('Y-m-d H:i') ?: '-' }}</td>
                                <td class="px-3 py-2">
                                    <form method="POST" action="{{ route('meta.whatsapps.subscription-jobs.failed.retry', $failed) }}">
                                        @csrf
                                        <button class="px-3 py-1.5 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 border border-white/10 text-xs text-white">Reprocesar</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-8 text-center text-white/60">No hay jobs fallidos.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $failedJobs->links() }}</div>
        </section>
    </div>
@endsection
