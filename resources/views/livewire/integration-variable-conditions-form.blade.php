<div class="rounded-2xl border border-white/10 bg-white/5 p-4">
    <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-base font-semibold text-white">Variables condicionales</h3>
            <p class="text-sm text-white/50">Define valores que se resuelven por reglas antes de enviarse en el body.</p>
        </div>
        <button type="button"
                wire:click="addCondition"
                class="rounded-xl border border-white/10 bg-indigo-500/30 px-3 py-2 text-white hover:bg-indigo-500/40">
            Agregar condicion
        </button>
    </div>

    <div class="mb-4 rounded-xl border border-white/10 bg-slate-900/50 p-3 text-xs text-white/60">
        <div>Ejemplo: variable <span class="font-mono text-white">campi_origien</span>, fuente <span class="font-mono text-white">name</span>, operador <span class="font-mono text-white">Igual a</span>, comparacion <span class="font-mono text-white">null</span>, valor <span class="font-mono text-white">meta</span>.</div>
        <div class="mt-1">En el body se usa como <span class="font-mono text-white">&#123;&#123;campi_origien&#125;&#125;</span>.</div>
    </div>

    @error('integration_variable_conditions')
        <div class="mb-2 text-sm text-rose-300">{{ $message }}</div>
    @enderror

    <div class="overflow-x-auto rounded-xl border border-white/10">
        <table class="min-w-full text-sm">
            <thead class="bg-white/5 text-white/70">
                <tr>
                    <th class="px-3 py-2 text-left">Variable destino</th>
                    <th class="px-3 py-2 text-left">Uso en body</th>
                    <th class="px-3 py-2 text-left">Fuente</th>
                    <th class="px-3 py-2 text-left">Operador</th>
                    <th class="px-3 py-2 text-left">Comparacion</th>
                    <th class="px-3 py-2 text-left">Valor a enviar</th>
                    <th class="px-3 py-2 text-left">Tipo</th>
                    <th class="px-3 py-2 text-left">Activa</th>
                    <th class="w-24 px-3 py-2 text-left">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/10 text-white/80">
                @forelse($conditions as $index => $condition)
                    <tr class="hover:bg-white/5" wire:key="integration-variable-condition-{{ $condition['key'] }}">
                        <td class="px-3 py-2 align-top">
                            <input type="hidden" name="integration_variable_conditions[{{ $index }}][order]" value="{{ $index }}">
                            <input name="integration_variable_conditions[{{ $index }}][target_variable]"
                                   wire:model.live.debounce.400ms="conditions.{{ $index }}.target_variable"
                                   value="{{ $condition['target_variable'] }}"
                                   class="w-full min-w-44 rounded-xl border border-white/10 bg-slate-900/60 p-2 font-mono text-white"
                                   placeholder="campi_origien">
                            @error("integration_variable_conditions.$index.target_variable") <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
                        </td>
                        <td class="px-3 py-2 align-top">
                            @php($targetUsageName = ($condition['target_variable'] ?? '') !== '' ? $condition['target_variable'] : 'campi_origien')
                            <div class="min-w-44 rounded-xl border border-white/10 bg-slate-900/60 p-2 font-mono text-xs text-white">
                                &#123;&#123;{{ $targetUsageName }}&#125;&#125;
                            </div>
                        </td>
                        <td class="px-3 py-2 align-top">
                            <select name="integration_variable_conditions[{{ $index }}][source_type]"
                                    wire:model.live="conditions.{{ $index }}.source_type"
                                    class="mb-2 w-full min-w-40 rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white">
                                @foreach($sourceTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>

                            @if(($condition['source_type'] ?? 'lead') === 'variable')
                                <input name="integration_variable_conditions[{{ $index }}][source_key]"
                                       wire:model.live.debounce.400ms="conditions.{{ $index }}.source_key"
                                       list="integration-condition-variables-{{ $index }}"
                                       value="{{ $condition['source_key'] }}"
                                       class="w-full min-w-44 rounded-xl border border-white/10 bg-slate-900/60 p-2 font-mono text-white"
                                       placeholder="mi_variable">
                                <datalist id="integration-condition-variables-{{ $index }}">
                                    @foreach($variableNames as $name)
                                        <option value="{{ $name }}"></option>
                                    @endforeach
                                </datalist>
                            @else
                                <select name="integration_variable_conditions[{{ $index }}][source_key]"
                                        wire:model.live="conditions.{{ $index }}.source_key"
                                        class="w-full min-w-44 rounded-xl border border-white/10 bg-slate-900/60 p-2 font-mono text-white">
                                    <option value="">Seleccione...</option>
                                    @foreach($leadFields as $field)
                                        <option value="{{ $field }}">{{ $field }}</option>
                                    @endforeach
                                </select>
                            @endif
                            @error("integration_variable_conditions.$index.source_key") <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
                        </td>
                        <td class="px-3 py-2 align-top">
                            <select name="integration_variable_conditions[{{ $index }}][operator]"
                                    wire:model.live="conditions.{{ $index }}.operator"
                                    class="w-full min-w-52 rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white">
                                @foreach($operators as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error("integration_variable_conditions.$index.operator") <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
                        </td>
                        <td class="px-3 py-2 align-top">
                            <textarea name="integration_variable_conditions[{{ $index }}][comparison_value]"
                                      wire:model.live.debounce.400ms="conditions.{{ $index }}.comparison_value"
                                      rows="2"
                                      class="w-full min-w-52 rounded-xl border border-white/10 bg-slate-900/60 p-2 font-mono text-xs text-white"
                                      placeholder="null">{{ $condition['comparison_value'] }}</textarea>
                            @error("integration_variable_conditions.$index.comparison_value") <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
                        </td>
                        <td class="px-3 py-2 align-top">
                            <textarea name="integration_variable_conditions[{{ $index }}][result_value]"
                                      wire:model.live.debounce.400ms="conditions.{{ $index }}.result_value"
                                      rows="2"
                                      class="w-full min-w-52 rounded-xl border border-white/10 bg-slate-900/60 p-2 font-mono text-xs text-white"
                                      placeholder="meta">{{ $condition['result_value'] }}</textarea>
                            @error("integration_variable_conditions.$index.result_value") <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
                        </td>
                        <td class="px-3 py-2 align-top">
                            <select name="integration_variable_conditions[{{ $index }}][result_type]"
                                    wire:model.live="conditions.{{ $index }}.result_type"
                                    class="w-full min-w-36 rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white">
                                @foreach($types as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error("integration_variable_conditions.$index.result_type") <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
                        </td>
                        <td class="px-3 py-2 align-top">
                            <label class="inline-flex items-center gap-2">
                                <input type="hidden" name="integration_variable_conditions[{{ $index }}][active]" value="0">
                                <input type="checkbox"
                                       name="integration_variable_conditions[{{ $index }}][active]"
                                       value="1"
                                       wire:model.live="conditions.{{ $index }}.active"
                                       class="rounded border-white/10 bg-slate-900/60">
                                <span>Si</span>
                            </label>
                        </td>
                        <td class="px-3 py-2 align-top">
                            <button type="button"
                                    wire:click="removeCondition({{ $index }})"
                                    class="rounded-lg border border-rose-300/20 bg-rose-500/20 px-3 py-1.5 text-white hover:bg-rose-500/30">
                                Quitar
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="px-3 py-8 text-center text-white/60" colspan="9">Aun no hay variables condicionales configuradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
