<section class="space-y-3 border-t border-white/10 pt-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-base font-semibold text-white">Variables del body</h3>
            <p class="text-sm text-white/50">Crea valores reutilizables para los payloads.</p>
        </div>
        <button type="button" wire:click="addVariable" class="rounded-lg border border-white/10 bg-indigo-500/30 px-3 py-2 text-white hover:bg-indigo-500/40">Agregar variable</button>
    </div>

    <div class="space-y-3">
        @forelse($variables as $index => $variable)
            <div wire:key="variable-{{ $variable['_key'] }}" class="grid grid-cols-1 gap-3 border border-white/10 bg-white/5 p-3 lg:grid-cols-6">
                <div>
                    <label class="{{ $labelClass }}">Nombre</label>
                    <input name="integration_variables[{{ $index }}][name]" wire:model.blur="variables.{{ $index }}.name" class="{{ $inputClass }} font-mono" placeholder="mi_variable" required>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Uso en body</label>
                    <div class="overflow-hidden border border-white/10 bg-slate-900/60 p-2 font-mono text-xs text-white">&#123;&#123;{{ $variable['name'] ?: 'mi_variable' }}&#125;&#125;</div>
                </div>
                <div class="lg:col-span-2">
                    <label class="{{ $labelClass }}">Valor</label>
                    <textarea name="integration_variables[{{ $index }}][value]" wire:model.blur="variables.{{ $index }}.value" rows="3" class="{{ $inputClass }} font-mono text-xs" required></textarea>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Tipo</label>
                    <select name="integration_variables[{{ $index }}][type]" wire:model.change="variables.{{ $index }}.type" class="{{ $inputClass }}">
                        @foreach($this->variableTypes() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                    <input type="hidden" name="integration_variables[{{ $index }}][active]" value="0">
                    <label class="mt-2 inline-flex items-center gap-2 text-sm text-white/80">
                        <input type="checkbox" name="integration_variables[{{ $index }}][active]" value="1" wire:model.change="variables.{{ $index }}.active" class="rounded border-white/20 bg-slate-900 text-indigo-500"> Activa
                    </label>
                </div>
                <div class="flex items-end justify-end">
                    <input type="hidden" name="integration_variables[{{ $index }}][order]" value="{{ $index }}">
                    <button type="button" wire:click="removeVariable({{ $index }})" class="rounded-lg border border-rose-300/20 bg-rose-500/20 px-3 py-2 text-white hover:bg-rose-500/30">Quitar</button>
                </div>
            </div>
        @empty
            <p class="border border-dashed border-white/15 p-5 text-center text-sm text-white/50 bg-white/5 rounded-xl">Aun no hay variables personalizadas.</p>
        @endforelse
    </div>
</section>
