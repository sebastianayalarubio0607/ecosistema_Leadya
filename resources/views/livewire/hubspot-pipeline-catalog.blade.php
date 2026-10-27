<section class="space-y-5 rounded-2xl border border-white/10 bg-zinc-950/25 p-5 text-white/80 backdrop-blur sm:p-6" aria-label="Sincronización de pipelines HubSpot">
    <div class="rounded-xl border border-white/10 bg-white/5 p-4">
        <h3 class="text-lg font-semibold text-white">Sincronizar etapas de HubSpot</h3>
        <p class="mt-1 text-sm text-white/60">Selecciona el pipeline cuyas etapas quieres sincronizar. Esa selección también se usará para consultar y procesar los estados por API.</p>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <button type="button" wire:click="loadPipelines" wire:loading.attr="disabled"
                class="rounded-xl border border-white/10 bg-indigo-500/30 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-500/40 disabled:cursor-not-allowed disabled:opacity-50">
            Actualizar pipelines
        </button>
    </div>

    <p wire:loading wire:target="loadPipelines" class="rounded-xl border border-indigo-300/20 bg-indigo-500/10 px-3 py-2 text-sm text-indigo-100" role="status">Consultando pipelines de HubSpot…</p>

    <div wire:loading.remove wire:target="loadPipelines" class="space-y-4" aria-live="polite">
        @if($catalogError !== '')
            <p class="rounded-xl border border-rose-300/20 bg-rose-500/10 p-3 text-sm text-rose-100" role="alert">{{ $catalogError }}</p>
        @elseif($loaded && $pipelines === [])
            <p class="rounded-xl border border-white/10 bg-white/5 p-4 text-sm text-white/60">No se encontraron pipelines de negocios en esta cuenta.</p>
        @elseif($loaded)
            <div class="rounded-xl border border-white/10 bg-slate-900/40 p-4">
                <label for="hubspot-pipeline-{{ $integrationId }}" class="mb-1.5 block text-sm font-medium text-white/75">Pipeline</label>
                <select id="hubspot-pipeline-{{ $integrationId }}" wire:model.live="pipelineId" class="w-full rounded-xl border border-white/10 bg-slate-900/60 px-3 py-2.5 text-white shadow-sm transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-400/30 [color-scheme:dark]" style="color-scheme: dark">
                    <option value="" class="bg-slate-900 text-white">Selecciona un pipeline</option>
                    @foreach($pipelines as $pipeline)
                        <option value="{{ $pipeline['id'] }}" class="bg-slate-900 text-white">{{ $pipeline['name'] }} ({{ $pipeline['id'] }})</option>
                    @endforeach
                </select>
                @if(filled($integration->hubspot_pipeline_id ?? null))
                    <p class="mt-2 text-xs text-white/50">Pipeline actualmente sincronizado: {{ $integration->hubspot_pipeline_id }}</p>
                @endif
            </div>
        @endif

        @if($selectedPipeline)
            <div class="overflow-x-auto rounded-xl border border-white/10 bg-slate-900/40">
                <table class="min-w-full text-left text-sm">
                    <caption class="border-b border-white/10 bg-white/5 px-4 py-3 text-left font-semibold text-white">
                        Etapas de {{ $selectedPipeline['name'] }} ({{ count($selectedPipeline['stages']) }})
                    </caption>
                    <thead class="bg-white/5 text-xs uppercase tracking-wide text-white/55"><tr>
                        <th class="px-3 py-2">Orden</th><th class="px-3 py-2">Etapa</th><th class="px-3 py-2">ID</th>
                    </tr></thead>
                    <tbody class="divide-y divide-white/10">
                        @forelse($selectedPipeline['stages'] as $index => $stage)
                            <tr wire:key="hubspot-stage-{{ $selectedPipeline['id'] }}-{{ $stage['id'] }}" class="transition hover:bg-white/5">
                                <td class="px-3 py-2">{{ $stage['displayOrder'] ?? $index }}</td>
                                <td class="px-3 py-2">{{ $stage['label'] ?? $stage['id'] }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ $stage['id'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-3 py-4 text-white/50">Este pipeline no tiene etapas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($syncUrl)
                <form method="POST" action="{{ $syncUrl }}" class="flex justify-end border-t border-white/10 pt-4">
                    @csrf
                    <input type="hidden" name="pipeline_id" value="{{ $selectedPipeline['id'] }}">
                    <button type="submit" class="rounded-xl border border-emerald-300/20 bg-emerald-500/25 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-500/35 focus:outline-none focus:ring-2 focus:ring-emerald-300/50">
                        Sincronizar etapas de este pipeline
                    </button>
                </form>
            @endif
        @endif
    </div>
</section>
