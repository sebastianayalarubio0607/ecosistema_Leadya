<div wire:poll.15s class="space-y-5 rounded-2xl border border-white/10 bg-zinc-950/25 p-5 text-white/80 backdrop-blur sm:p-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-white">Consulta de estados de HubSpot</h3>
            <p class="mt-1 text-sm text-white/55">Ejecuciones y cambios de estado conservados durante siete días. El webhook permanece activo en ambos modos.</p>
        </div>
        @if($canRun)
            <button type="button" wire:click="queueSync" wire:loading.attr="disabled"
                    class="rounded-xl border border-indigo-300/20 bg-indigo-500/30 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-500/40 disabled:opacity-50">
                <span wire:loading.remove wire:target="queueSync">Consultar ahora</span>
                <span wire:loading wire:target="queueSync">Encolando…</span>
            </button>
        @else
            <p class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs text-white/60">Activa el modo API y sincroniza un pipeline para habilitar la consulta.</p>
        @endif
    </div>

    @if($notice !== '')
        <p class="rounded-xl border border-emerald-300/20 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-100" role="status">{{ $notice }}</p>
    @endif
    @error('sync') <p class="rounded-xl border border-rose-300/20 bg-rose-500/10 px-3 py-2 text-sm text-rose-100" role="alert">{{ $message }}</p> @enderror

    <section class="space-y-3">
        <h4 class="text-sm font-semibold text-white">Ejecuciones recientes</h4>
        <div class="overflow-x-auto rounded-xl border border-white/10">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-white/5 text-xs uppercase tracking-wide text-white/50"><tr>
                    <th class="px-3 py-2">Inicio</th><th class="px-3 py-2">Origen</th><th class="px-3 py-2">Resultado</th><th class="px-3 py-2">Oportunidades</th><th class="px-3 py-2">Leads actualizados</th><th class="px-3 py-2">Sin coincidencia</th>
                </tr></thead>
                <tbody class="divide-y divide-white/10">
                    @forelse($runs as $run)
                        <tr>
                            <td class="whitespace-nowrap px-3 py-2">{{ $run->started_at?->format('Y-m-d H:i:s') ?? $run->created_at?->format('Y-m-d H:i:s') }}</td>
                            <td class="px-3 py-2">{{ match($run->trigger_source) { 'scheduled' => 'Programada', 'manual-integration' => 'Manual', 'webhook' => 'Webhook', default => $run->trigger_source } }}</td>
                            <td class="px-3 py-2"><span class="rounded-lg border border-white/10 bg-white/5 px-2 py-1 text-xs">{{ match($run->status) { 'pending' => 'Pendiente', 'running' => 'En curso', 'completed' => 'Completada', 'failed' => 'Fallida', default => $run->status } }}</span>@if($run->error_message)<p class="mt-1 max-w-xs text-xs text-rose-200">{{ $run->error_message }}</p>@endif</td>
                            <td class="px-3 py-2">{{ $run->opportunities_checked }}</td><td class="px-3 py-2">{{ $run->leads_updated }}</td><td class="px-3 py-2">{{ $run->leads_not_found }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-6 text-center text-white/45">Todavía no hay ejecuciones.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="space-y-3">
        <h4 class="text-sm font-semibold text-white">Leads y estados procesados</h4>
        <div class="overflow-x-auto rounded-xl border border-white/10">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-white/5 text-xs uppercase tracking-wide text-white/50"><tr>
                    <th class="px-3 py-2">Fecha</th><th class="px-3 py-2">Lead / oportunidad</th><th class="px-3 py-2">Estado anterior → nuevo</th><th class="px-3 py-2">Resultado</th><th class="px-3 py-2">Detalle</th>
                </tr></thead>
                <tbody class="divide-y divide-white/10">
                    @forelse($logs as $log)
                        <tr>
                            <td class="whitespace-nowrap px-3 py-2">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                            <td class="px-3 py-2"><div>{{ trim(($log->lead?->name ?? '').' '.($log->lead?->last_name ?? '')) ?: 'Lead no encontrado' }}</div><div class="font-mono text-xs text-white/45">{{ $log->opportunity_id }}</div></td>
                            <td class="px-3 py-2"><div class="max-w-xs truncate" title="{{ $log->previous_crm_state }}">{{ $log->previous_crm_state ?? '—' }}</div><div class="max-w-xs truncate text-emerald-100" title="{{ $log->new_crm_state }}">{{ $log->new_crm_state ?? '—' }}</div></td>
                            <td class="px-3 py-2">{{ match($log->state_status) { 'updated' => 'Actualizado', 'unchanged' => 'Sin avance', 'not_found' => 'Sin coincidencia', 'skipped' => 'Ignorado', 'error' => 'Error', default => $log->state_status } }}</td>
                            <td class="px-3 py-2 text-xs text-white/55">{{ $log->message ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-6 text-center text-white/45">No hay cambios registrados durante los últimos siete días.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $logs->links() }}</div>
    </section>
</div>
