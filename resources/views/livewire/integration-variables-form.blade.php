<div class="rounded-2xl border border-white/10 bg-white/5 p-4">
    <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-base font-semibold text-white">Variables del body</h3>
            <p class="text-sm text-white/50">Crea valores reutilizables para usarlos en los payloads.</p>
        </div>
        <button type="button"
                wire:click="addVariable"
                class="rounded-xl border border-white/10 bg-indigo-500/30 px-3 py-2 text-white hover:bg-indigo-500/40">
            Agregar variable
        </button>
    </div>

    <div class="mb-4 rounded-xl border border-white/10 bg-slate-900/50 p-3 text-xs text-white/60">
        <div>En el body usa <span class="font-mono text-white">&#123;&#123;mi_variable&#125;&#125;</span> o <span class="font-mono text-white">&#123;&#123; $variables-&gt;mi_variable &#125;&#125;</span>.</div>
        <div class="mt-1">Ejemplo de valor: <span class="font-mono text-white">nueva oportunidad en el servicio: &#123;&#123; $lead-&gt;referencia &#125;&#125; proveniente de &#123;&#123; $lead-&gt;plataforma &#125;&#125;</span></div>
    </div>

    @error('integration_variables')
        <div class="mb-2 text-sm text-rose-300">{{ $message }}</div>
    @enderror

    <div class="overflow-x-auto rounded-xl border border-white/10">
        <table class="min-w-full text-sm">
            <thead class="bg-white/5 text-white/70">
                <tr>
                    <th class="px-3 py-2 text-left">Nombre</th>
                    <th class="px-3 py-2 text-left">Uso en body</th>
                    <th class="px-3 py-2 text-left">Valor</th>
                    <th class="px-3 py-2 text-left">Tipo</th>
                    <th class="px-3 py-2 text-left">Activa</th>
                    <th class="w-24 px-3 py-2 text-left">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/10 text-white/80">
                @forelse($variables as $index => $variable)
                    <tr class="hover:bg-white/5" wire:key="integration-variable-{{ $variable['key'] }}">
                        <td class="px-3 py-2 align-top">
                            <input type="hidden" name="integration_variables[{{ $index }}][order]" value="{{ $index }}">
                            <input name="integration_variables[{{ $index }}][name]"
                                   wire:model.live.debounce.400ms="variables.{{ $index }}.name"
                                   value="{{ $variable['name'] }}"
                                   class="w-full min-w-44 rounded-xl border border-white/10 bg-slate-900/60 p-2 font-mono text-white"
                                   placeholder="mi_variable">
                            @error("integration_variables.$index.name") <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
                        </td>
                        <td class="px-3 py-2 align-top">
                            @php($bodyUsageName = ($variable['name'] ?? '') !== '' ? $variable['name'] : 'mi_variable')
                            <div class="min-w-44 rounded-xl border border-white/10 bg-slate-900/60 p-2 font-mono text-xs text-white">
                                &#123;&#123;{{ $bodyUsageName }}&#125;&#125;
                            </div>
                        </td>
                        <td class="px-3 py-2 align-top">
                            <textarea name="integration_variables[{{ $index }}][value]"
                                      wire:model.live.debounce.400ms="variables.{{ $index }}.value"
                                      rows="3"
                                      class="w-full min-w-72 rounded-xl border border-white/10 bg-slate-900/60 p-2 font-mono text-xs text-white"
                                      placeholder="Valor o template con campos del lead">{{ $variable['value'] }}</textarea>
                            @error("integration_variables.$index.value") <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
                        </td>
                        <td class="px-3 py-2 align-top">
                            <select name="integration_variables[{{ $index }}][type]"
                                    wire:model.live="variables.{{ $index }}.type"
                                    class="w-full min-w-36 rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white">
                                @foreach($types as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error("integration_variables.$index.type") <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
                        </td>
                        <td class="px-3 py-2 align-top">
                            <label class="inline-flex items-center gap-2">
                                <input type="hidden" name="integration_variables[{{ $index }}][active]" value="0">
                                <input type="checkbox"
                                       name="integration_variables[{{ $index }}][active]"
                                       value="1"
                                       wire:model.live="variables.{{ $index }}.active"
                                       class="rounded border-white/10 bg-slate-900/60">
                                <span>Si</span>
                            </label>
                        </td>
                        <td class="px-3 py-2 align-top">
                            <button type="button"
                                    wire:click="removeVariable({{ $index }})"
                                    class="rounded-lg border border-rose-300/20 bg-rose-500/20 px-3 py-1.5 text-white hover:bg-rose-500/30">
                                Quitar
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="px-3 py-8 text-center text-white/60" colspan="6">Aun no hay variables personalizadas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
