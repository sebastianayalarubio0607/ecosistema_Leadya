<section class="space-y-4 rounded-xl border border-white/10 bg-white/5 p-4" aria-label="Pipelines de Freshworks">
    <div>
        <h4 class="text-sm font-semibold text-white">Consulta de pipelines y stages</h4>
        <p class="mt-1 text-xs text-white/50">Usa la URL principal y el token guardados en esta integración.</p>
    </div>
    <button type="button" wire:click="loadPipelines" wire:loading.attr="disabled" class="rounded-lg border border-indigo-300/20 bg-indigo-500/30 px-4 py-2 text-sm text-white hover:bg-indigo-500/40 disabled:opacity-50">Actualizar pipelines</button>
    <p wire:loading wire:target="loadPipelines,updatedPipelineId" class="text-sm text-indigo-200">Consultando Freshworks...</p>
    @if($catalogError !== '')
        <p class="rounded-lg border border-rose-300/20 bg-rose-500/10 p-3 text-sm text-rose-100">{{ $catalogError }}</p>
    @elseif($loaded && $pipelines === [])
        <p class="text-sm text-white/60">No se encontraron pipelines.</p>
    @elseif($loaded)
        <div>
            <label for="freshworks-pipeline-{{ $integrationId }}" class="mb-1 block text-sm font-medium text-white/70">Pipeline</label>
            <select id="freshworks-pipeline-{{ $integrationId }}" wire:model.live="pipelineId" class="w-full rounded-lg border border-white/10 bg-slate-900/60 p-2 text-white focus:border-indigo-400 focus:ring-indigo-400">
                <option value="">Selecciona un pipeline</option>
                @foreach($pipelines as $pipeline)<option value="{{ $pipeline['id'] }}">{{ $pipeline['name'] }}</option>@endforeach
            </select>
        </div>
    @endif
    @if($selectedPipeline)
        <div class="overflow-x-auto rounded-lg border border-white/10">
            <table class="min-w-full text-left text-xs text-white/80">
                <caption class="bg-white/5 px-3 py-2 text-left text-sm font-semibold text-white">Stages de {{ $selectedPipeline['name'] }}</caption>
                <thead class="bg-white/5 text-white/60"><tr><th class="px-3 py-2">Nombre</th><th class="px-3 py-2">ID del stage</th></tr></thead>
                <tbody class="divide-y divide-white/10">
                    @forelse($stages as $stage)<tr wire:key="freshworks-stage-{{ $pipelineId }}-{{ $stage['id'] }}"><td class="px-3 py-2">{{ $stage['name'] }}</td><td class="px-3 py-2 font-mono">{{ $stage['id'] }}</td></tr>
                    @empty<tr><td colspan="2" class="px-3 py-4 text-white/50">Este pipeline no tiene stages.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        @if($syncUrl)<form method="POST" action="{{ $syncUrl }}" class="flex justify-end">@csrf<input type="hidden" name="pipeline_id" value="{{ $selectedPipeline['id'] }}"><button type="submit" class="rounded-lg border border-emerald-300/20 bg-emerald-500/25 px-4 py-2 text-sm text-white hover:bg-emerald-500/35">Sincronizar estados</button></form>@endif
    @endif
</section>
