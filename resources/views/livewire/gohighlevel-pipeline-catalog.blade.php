@php
    $catalogInputClass = filled($inputClass)
        ? $inputClass
        : 'w-full rounded-xl border border-white/10 bg-slate-900/60 px-3 py-2.5 text-white placeholder-white/40 shadow-sm shadow-black/10 transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-400/30';
    $catalogLabelClass = filled($labelClass) ? $labelClass : 'mb-1.5 block text-sm font-medium text-white/75';
@endphp

<section class="space-y-5 rounded-2xl border border-white/10 bg-zinc-950/25 p-5 text-white/80 backdrop-blur sm:p-6" aria-label="Consulta de pipelines">
    <div class="rounded-xl border border-white/10 bg-white/5 p-4">
        <h4 class="text-base font-semibold text-white">Consulta de pipelines y stages</h4>
        <p class="mt-1 text-sm text-white/60">Consulta con el token guardado y el locationId de esta integración. No modifica los JSON de envío.</p>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <button type="button" wire:click="loadPipelines" wire:loading.attr="disabled"
                class="rounded-xl border border-white/10 bg-indigo-500/30 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-500/40 focus:outline-none focus:ring-2 focus:ring-indigo-400/60 disabled:cursor-not-allowed disabled:opacity-50">
            Actualizar pipelines
        </button>
    </div>
    <div id="ghl-location-error-{{ $integrationId }}">
        @error('locationId') <p class="rounded-xl border border-rose-300/20 bg-rose-500/10 px-3 py-2 text-sm text-rose-100" role="alert">{{ $message }}</p> @enderror
    </div>

    <p wire:loading wire:target="loadPipelines" class="rounded-xl border border-indigo-300/20 bg-indigo-500/10 px-3 py-2 text-sm text-indigo-100" role="status">Consultando pipelines...</p>

    <div wire:loading.remove wire:target="loadPipelines" class="space-y-4" aria-live="polite">
        @if($catalogError !== '')
            <p class="rounded-xl border border-rose-300/20 bg-rose-500/10 p-3 text-sm text-rose-100" role="alert">{{ $catalogError }}</p>
        @elseif($loaded && $pipelines === [])
            <p class="rounded-xl border border-white/10 bg-white/5 p-4 text-sm text-white/60">No se encontraron pipelines para este locationId.</p>
        @elseif($loaded)
            <div class="rounded-xl border border-white/10 bg-slate-900/40 p-4">
                <label for="ghl-pipeline-{{ $integrationId }}" class="{{ $catalogLabelClass }}">Pipeline</label>
                <select id="ghl-pipeline-{{ $integrationId }}" wire:model.live="pipelineId" class="{{ $catalogInputClass }} bg-slate-900 text-white [color-scheme:dark]" style="color-scheme: dark">
                    <option value="" class="bg-slate-900 text-white">Selecciona un pipeline</option>
                    @foreach($pipelines as $pipeline)
                        <option value="{{ $pipeline['id'] }}" class="bg-slate-900 text-white">{{ $pipeline['name'] }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @if($selectedPipeline)
            <dl class="grid gap-3 rounded-xl border border-white/10 bg-slate-900/40 p-4 text-sm sm:grid-cols-2">
                @foreach(['name' => 'Nombre', 'id' => 'ID del pipeline', 'dateAdded' => 'Fecha de creación', 'dateUpdated' => 'Última actualización'] as $field => $label)
                    <div class="rounded-lg border border-white/10 bg-slate-900/60 p-3"><dt class="text-xs text-white/50">{{ $label }}</dt><dd class="mt-1 break-all text-white/90">{{ $selectedPipeline[$field] ?? '—' }}</dd></div>
                @endforeach
                @foreach(['showInFunnel' => 'Mostrar en funnel', 'showInPieChart' => 'Mostrar en gráfico circular', 'useOpportunityProbability' => 'Usar probabilidad de oportunidad'] as $field => $label)
                    <div class="rounded-lg border border-white/10 bg-slate-900/60 p-3"><dt class="text-xs text-white/50">{{ $label }}</dt><dd class="mt-1 text-white/90">{{ !isset($selectedPipeline[$field]) ? '—' : ($selectedPipeline[$field] ? 'Sí' : 'No') }}</dd></div>
                @endforeach
            </dl>
            <div class="overflow-x-auto rounded-xl border border-white/10 bg-slate-900/40">
                <table class="min-w-full text-left text-xs text-white/80">
                    <caption class="border-b border-white/10 bg-white/5 px-4 py-3 text-left text-sm font-semibold text-white">Stages ({{ count($selectedPipeline['stages']) }})</caption>
                    <thead class="bg-white/5 text-white/60">
                        <tr>
                            @foreach(['Posición', 'Nombre', 'ID del stage', 'Mostrar en funnel', 'Gráfico circular', 'Probabilidad (%)', 'originId'] as $heading)
                                <th scope="col" class="whitespace-nowrap px-3 py-2 font-medium">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                        @forelse($selectedPipeline['stages'] as $stage)
                            <tr wire:key="ghl-stage-{{ $selectedPipeline['id'] }}-{{ $stage['id'] }}" class="transition hover:bg-white/5">
                                <td class="px-3 py-2">{{ $stage['position'] ?? '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-2">{{ $stage['name'] }}</td>
                                <td class="px-3 py-2 font-mono">{{ $stage['id'] }}</td>
                                @foreach(['showInFunnel', 'showInPieChart'] as $field)
                                    <td class="px-3 py-2">{{ !isset($stage[$field]) ? '—' : ($stage[$field] ? 'Sí' : 'No') }}</td>
                                @endforeach
                                <td class="px-3 py-2">{{ $stage['stageWinProbability'] ?? '—' }}</td>
                                <td class="px-3 py-2 font-mono">{{ $stage['originId'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-3 py-4 text-white/50">Este pipeline no tiene stages.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($syncUrl)
                <form method="POST" action="{{ $syncUrl }}" class="flex justify-end border-t border-white/10 pt-4">
                    @csrf
                    <input type="hidden" name="pipeline_id" value="{{ $selectedPipeline['id'] }}">
                    <button type="submit" class="rounded-xl border border-emerald-300/20 bg-emerald-500/25 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-500/35 focus:outline-none focus:ring-2 focus:ring-emerald-300/50">
                        Sincronizar estados
                    </button>
                </form>
            @endif
        @endif
    </div>
</section>
