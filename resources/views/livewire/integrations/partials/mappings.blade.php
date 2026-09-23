@php
    $isFreshworksMapping = $scope === 'freshworks';
    $fieldName = $isFreshworksMapping ? 'freshworks_variable_mappings' : 'integration_variable_mappings';
    $stateName = $isFreshworksMapping ? 'freshworksMappings' : 'integrationMappings';
    $addMethod = $isFreshworksMapping ? 'addFreshworksMapping' : 'addIntegrationMapping';
    $removeMethod = $isFreshworksMapping ? 'removeFreshworksMapping' : 'removeIntegrationMapping';
@endphp

<section class="space-y-4 rounded-2xl border border-white/10 bg-white/5 p-5 shadow-sm shadow-black/10 {{ $isFreshworksMapping ? '' : 'sm:p-6' }}">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-base font-semibold text-white">Mapeo de variables</h3>
            <p class="text-sm text-white/50">Normaliza valores del lead antes de construir el payload.</p>
        </div>
        <button type="button" wire:click="{{ $addMethod }}" class="rounded-xl border border-indigo-300/20 bg-indigo-500/30 px-3 py-2 text-white transition hover:bg-indigo-500/40">Agregar variable</button>
    </div>

    <div class="space-y-3 rounded-xl border border-white/10 bg-zinc-950/20 p-3">
        @forelse($items as $index => $mapping)
            <div wire:key="{{ $scope }}-mapping-{{ $mapping['_key'] }}" class="grid grid-cols-1 gap-3 rounded-xl border border-white/10 bg-slate-900/40 p-4 lg:grid-cols-6">
                <div>
                    <label class="{{ $labelClass }}">Variable</label>
                    <input name="{{ $fieldName }}[{{ $index }}][target_variable]" wire:model.blur="{{ $stateName }}.{{ $index }}.target_variable" class="{{ $inputClass }}" required>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Campo Lead</label>
                    <select name="{{ $fieldName }}[{{ $index }}][lead_field]" wire:model.change="{{ $stateName }}.{{ $index }}.lead_field" class="{{ $inputClass }}" required>
                        <option value="">Seleccione...</option>
                        @foreach($leadFields as $field)<option value="{{ $field }}">{{ $field }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Valor esperado</label>
                    <input name="{{ $fieldName }}[{{ $index }}][expected_value]" wire:model.blur="{{ $stateName }}.{{ $index }}.expected_value" class="{{ $inputClass }}" required>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Valor a enviar</label>
                    <input name="{{ $fieldName }}[{{ $index }}][mapped_value]" wire:model.blur="{{ $stateName }}.{{ $index }}.mapped_value" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Activa</label>
                    <input type="hidden" name="{{ $fieldName }}[{{ $index }}][active]" value="0">
                    <x-toggle-switch name="{{ $fieldName }}[{{ $index }}][active]" value="1" wire:model.change="{{ $stateName }}.{{ $index }}.active" label="Sí" />
                </div>
                <div class="flex items-end justify-end">
                    <input type="hidden" name="{{ $fieldName }}[{{ $index }}][order]" value="{{ $index }}">
                    <button type="button" wire:click="{{ $removeMethod }}({{ $index }})" class="rounded-lg border border-rose-300/20 bg-rose-500/20 px-3 py-2 text-white hover:bg-rose-500/30">Quitar</button>
                </div>
            </div>
        @empty
            <p class="border border-dashed border-white/15 p-5 text-center text-sm text-white/50 bg-white/5 rounded-xl">No hay mapeos configurados.</p>
        @endforelse
    </div>
</section>
