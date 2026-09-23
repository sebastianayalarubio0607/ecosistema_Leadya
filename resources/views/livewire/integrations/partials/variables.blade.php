<section class="space-y-4 rounded-2xl border border-white/10 bg-white/5 p-5 shadow-sm shadow-black/10 sm:p-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-base font-semibold text-white">Variables del body</h3>
            <p class="text-sm text-white/50">Crea valores reutilizables para los payloads.</p>
        </div>
        <button type="button" wire:click="addVariable" class="rounded-xl border border-indigo-300/20 bg-indigo-500/30 px-3 py-2 text-white transition hover:bg-indigo-500/40">Agregar variable</button>
    </div>

    <div class="rounded-xl border border-sky-300/20 bg-sky-500/10 p-4 text-sm text-sky-50/90">
        <h4 class="font-semibold text-sky-100">Variables de atribución disponibles</h4>
        <p class="mt-1 text-sky-100/75">No necesitas crearlas: se resuelven al enviar el lead con los datos locales más recientes de Leadya. Nunca consultan Google, Meta ni el CRM destino.</p>
        <div class="mt-3 grid gap-2 md:grid-cols-2 xl:grid-cols-3">
            @foreach([
                'campaign_name' => 'Nombre de campaña Google o Meta; si aún no existe en el catálogo local, envía su ID.',
                'ad_group_name' => 'Nombre del grupo de anuncios: Google Ad Group o Meta Ad Set.',
                'ad_name' => 'Nombre del anuncio. En Google Ads, si la API no provee un nombre para ese tipo de anuncio, envía su ID.',
                'campaign_origin_name' => 'Nombre del origen; respalda con campaign_origin del lead.',
                'origin_name' => 'Alias de campaign_origin_name para payloads que usen el término origen.',
                'source_name' => 'Nombre de la fuente relacionada con el origen.',
                'platform_name' => 'Nombre de la plataforma; respalda con plataforma del lead.',
                'campaign_relation' => 'JSON con proveedor, ID, nombre y estado de resolución.',
                'ad_group_relation' => 'JSON con la relación del grupo de anuncios.',
                'ad_relation' => 'JSON con la relación del anuncio.',
                'attribution_relation' => 'JSON completo con campaña, origen, fuente y plataforma.',
            ] as $name => $description)
                <div class="rounded-lg border border-sky-200/10 bg-slate-950/25 p-2">
                    <code class="font-mono text-xs text-sky-200">&#123;&#123;{{ $name }}&#125;&#125;</code>
                    <p class="mt-1 text-xs text-sky-100/65">{{ $description }}</p>
                </div>
            @endforeach
        </div>
        <div class="mt-3 rounded-lg border border-white/10 bg-slate-950/40 p-3 font-mono text-xs text-sky-50/85">
            <p class="mb-1 font-sans text-xs text-sky-100/65">En JSON, usa las variables de texto entre comillas y las variables <span class="font-mono">_relation</span> sin comillas.</p>
            <div>{&quot;campaign_name&quot;: &quot;&#123;&#123;campaign_name&#125;&#125;&quot;, &quot;attribution&quot;: &#123;&#123;attribution_relation&#125;&#125;}</div>
        </div>
        <p class="mt-2 text-xs text-sky-100/60">Los caracteres se muestran codificados en esta ayuda para que Blade/Livewire no intente renderizarlos. Copia la sintaxis visible, con doble llave, al body.</p>
    </div>

    <div class="space-y-3">
        @forelse($variables as $index => $variable)
            <div wire:key="variable-{{ $variable['_key'] }}" class="grid grid-cols-1 gap-3 rounded-xl border border-white/10 bg-slate-900/40 p-4 lg:grid-cols-6">
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
                    <x-toggle-switch name="integration_variables[{{ $index }}][active]" value="1" wire:model.change="variables.{{ $index }}.active" label="Activa" />
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
