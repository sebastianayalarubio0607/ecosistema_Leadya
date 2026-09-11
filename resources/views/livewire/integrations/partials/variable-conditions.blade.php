@php($variableNames = collect($variables)->pluck('name')->filter()->unique()->values())

<section class="space-y-3 border-t border-white/10 pt-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-base font-semibold text-white">Variables condicionales</h3>
            <p class="text-sm text-white/50">Resuelve valores mediante reglas antes de enviar el body.</p>
        </div>
        <button type="button" wire:click="addVariableCondition" class="rounded-lg border border-white/10 bg-indigo-500/30 px-3 py-2 text-white hover:bg-indigo-500/40">Agregar condicion</button>
    </div>

    <div class="space-y-3 rounded-xl border border-white/10  p-3">
        @forelse($variableConditions as $index => $condition)
            <div wire:key="variable-condition-{{ $condition['_key'] }}" class="grid grid-cols-1 gap-3 border border-white/10 bg-white/5 p-3 lg:grid-cols-4 xl:grid-cols-8 rounded-xl">
                <div>
                    <label class="{{ $labelClass }}">Variable destino</label>
                    <input name="integration_variable_conditions[{{ $index }}][target_variable]" wire:model.blur="variableConditions.{{ $index }}.target_variable" class="{{ $inputClass }} font-mono" required>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Fuente</label>
                    <select name="integration_variable_conditions[{{ $index }}][source_type]" wire:model.live="variableConditions.{{ $index }}.source_type" class="{{ $inputClass }}">
                        <option value="lead">Lead</option><option value="variable">Variable del body</option>
                    </select>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Campo fuente</label>
                    @if(($condition['source_type'] ?? 'lead') === 'variable')
                        <select name="integration_variable_conditions[{{ $index }}][source_key]" wire:model.change="variableConditions.{{ $index }}.source_key" class="{{ $inputClass }}" required>
                            <option value="">Seleccione...</option>
                            @foreach($variableNames as $name)<option value="{{ $name }}">{{ $name }}</option>@endforeach
                        </select>
                    @else
                        <select name="integration_variable_conditions[{{ $index }}][source_key]" wire:model.change="variableConditions.{{ $index }}.source_key" class="{{ $inputClass }}" required>
                            <option value="">Seleccione...</option>
                            @foreach($leadFields as $field)<option value="{{ $field }}">{{ $field }}</option>@endforeach
                        </select>
                    @endif
                </div>
                <div>
                    <label class="{{ $labelClass }}">Operador</label>
                    <select name="integration_variable_conditions[{{ $index }}][operator]" wire:model.change="variableConditions.{{ $index }}.operator" class="{{ $inputClass }}">
                        @foreach($this->conditionOperators() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Comparacion</label>
                    <textarea name="integration_variable_conditions[{{ $index }}][comparison_value]" wire:model.blur="variableConditions.{{ $index }}.comparison_value" rows="2" class="{{ $inputClass }} font-mono text-xs"></textarea>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Valor a enviar</label>
                    <textarea name="integration_variable_conditions[{{ $index }}][result_value]" wire:model.blur="variableConditions.{{ $index }}.result_value" rows="2" class="{{ $inputClass }} font-mono text-xs"></textarea>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Tipo</label>
                    <select name="integration_variable_conditions[{{ $index }}][result_type]" wire:model.change="variableConditions.{{ $index }}.result_type" class="{{ $inputClass }}">
                        @foreach($this->variableTypes() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                    <input type="hidden" name="integration_variable_conditions[{{ $index }}][active]" value="0">
                    <label class="mt-2 inline-flex items-center gap-2 text-sm text-white/80"><input type="checkbox" name="integration_variable_conditions[{{ $index }}][active]" value="1" wire:model.change="variableConditions.{{ $index }}.active" class="rounded border-white/20 bg-slate-900 text-indigo-500"> Activa</label>
                </div>
                <div class="flex items-end justify-end">
                    <input type="hidden" name="integration_variable_conditions[{{ $index }}][order]" value="{{ $index }}">
                    <button type="button" wire:click="removeVariableCondition({{ $index }})" class="rounded-lg border border-rose-300/20 bg-rose-500/20 px-3 py-2 text-white hover:bg-rose-500/30">Quitar</button>
                </div>
            </div>
        @empty
            <p class="border border-dashed border-white/15 p-5 text-center text-sm text-white/50">Aun no hay variables condicionales.</p>
        @endforelse
    </div>
</section>
