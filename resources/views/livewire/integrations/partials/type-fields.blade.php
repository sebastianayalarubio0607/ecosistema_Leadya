@php
    $data = $typeData[$typeKey] ?? [];
    $sameStoredType = $isEdit && ! $typeChanged;
    $tokenStored = $sameStoredType && ($storedSecrets['tokent'] ?? false);
    $accessTokenStored = $sameStoredType && ($storedSecrets['access_token'] ?? false);
    $clientSecretStored = $sameStoredType && ($storedSecrets['client_secret'] ?? false);
    $refreshTokenStored = $sameStoredType && ($storedSecrets['refresh_token'] ?? false);
    $passwordStored = $sameStoredType && ($storedSecrets['password'] ?? false);
@endphp

@if($typeKey !== '')
    <section class="space-y-5 border-t border-white/10 pt-6">
        <div>
            <h3 class="text-base font-semibold text-white">Configuracion {{ collect($types)->firstWhere('key', $typeKey)['name'] ?? '' }}</h3>
            <p class="text-sm text-white/50">Solo se renderizan y envian los campos correspondientes a este tipo.</p>
        </div>

        @unless(in_array($typeKey, ['atom', 'lety'], true))
            <div>
                <label class="{{ $labelClass }}">
                    {{ $typeKey === 'hubspot' ? 'URL base HubSpot' : ($typeKey === 'freshworks_oportunidad' ? 'URL principal Freshworks' : ($typeKey === 'zapnito_invitacion' ? 'URL base Zapnito' : 'URL')) }}
                    {{ in_array($typeKey, ['gohighlevel', 'gohighlevel_oportunidad'], true) ? '' : '*' }}
                </label>
                <input name="url" type="url" wire:model.blur="typeData.{{ $typeKey }}.url"
                       class="{{ $inputClass }}"
                       placeholder="{{ $typeKey === 'hubspot' ? 'https://api.hubapi.com' : ($typeKey === 'zapnito_invitacion' ? 'https://comunidad.flar.com' : 'https://...') }}"
                       @required(!in_array($typeKey, ['gohighlevel', 'gohighlevel_oportunidad'], true))>
                @error('url') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
            </div>
        @endunless

        @switch($typeKey)
            @case('kommo')
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="{{ $labelClass }}">Token</label>
                        <input name="tokent" type="password" wire:model="typeData.kommo.tokent" class="{{ $inputClass }}"
                               autocomplete="new-password" placeholder="{{ $tokenStored ? 'Token guardado; deja vacio para conservarlo' : 'Token de Kommo' }}">
                    </div>
                    @foreach(['crm_Id_phone' => 'crm_Id_phone', 'crm_Id_email' => 'crm_Id_email', 'crm_Id_service' => 'crm_Id_service', 'crm_Id_fuente' => 'crm_Id_fuente'] as $field => $label)
                        <div>
                            <label class="{{ $labelClass }}">{{ $label }}</label>
                            <input name="{{ $field }}" wire:model.blur="typeData.kommo.{{ $field }}" class="{{ $inputClass }}">
                        </div>
                    @endforeach
                </div>
                @break

            @case('kommopipeline')
                <div @if($integrationId) wire:init="loadKommoPipelines" @endif class="space-y-5">
                    @if($integrationId)
                        <div class="flex justify-end">
                            <button type="button" wire:click="loadKommoPipelines"
                                    wire:loading.attr="disabled" wire:target="loadKommoPipelines"
                                    class="rounded-lg border border-white/10 bg-white/10 px-3 py-2 text-sm text-white hover:bg-white/15 disabled:opacity-50">
                                <span wire:loading.remove wire:target="loadKommoPipelines">Actualizar catalogo</span>
                                <span wire:loading wire:target="loadKommoPipelines">Consultando...</span>
                            </button>
                        </div>
                    @endif
                    <div>
                        <label class="{{ $labelClass }}">Token de acceso *</label>
                        <input name="tokent" type="password" wire:model="typeData.kommopipeline.tokent"
                               class="{{ $inputClass }}" autocomplete="new-password"
                               placeholder="{{ $tokenStored ? 'Token guardado; deja vacio para conservarlo' : 'Bearer token de Kommo' }}"
                               @required(!$tokenStored)>
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Payload JSON configurable *</label>
                        <textarea name="body" rows="14" wire:model.blur="typeData.kommopipeline.body" class="{{ $inputClass }} font-mono text-sm" required></textarea>
                    </div>
                    @if($kommoCatalogError !== '')
                        <p class="border border-amber-300/20 bg-amber-500/10 p-3 text-sm text-amber-100">{{ $kommoCatalogError }}</p>
                    @endif
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="{{ $labelClass }}">Pipeline por defecto</label>
                            <input type="hidden" name="kommo_pipeline_default_pipeline_name" value="{{ $data['kommo_pipeline_default_pipeline_name'] ?? '' }}">
                            <select name="kommo_pipeline_default_pipeline_id"
                                    wire:model="typeData.kommopipeline.kommo_pipeline_default_pipeline_id"
                                    wire:change="loadDefaultKommoStatuses" class="{{ $inputClass }}">
                                <option value="">{{ $integrationId ? 'Seleccione...' : 'Guarda URL y token primero' }}</option>
                                @if(($data['kommo_pipeline_default_pipeline_id'] ?? '') !== '' && !collect($kommoPipelines)->contains('id', (string) $data['kommo_pipeline_default_pipeline_id']))
                                    <option value="{{ $data['kommo_pipeline_default_pipeline_id'] }}">{{ $data['kommo_pipeline_default_pipeline_name'] ?: $data['kommo_pipeline_default_pipeline_id'] }}</option>
                                @endif
                                @foreach($kommoPipelines as $pipeline)
                                    <option value="{{ $pipeline['id'] }}">{{ $pipeline['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="{{ $labelClass }}">Status por defecto</label>
                            <input type="hidden" name="kommo_pipeline_default_status_name" value="{{ $data['kommo_pipeline_default_status_name'] ?? '' }}">
                            <select name="kommo_pipeline_default_status_id"
                                    wire:model="typeData.kommopipeline.kommo_pipeline_default_status_id"
                                    wire:change="setDefaultKommoStatusName" class="{{ $inputClass }}">
                                <option value="">Seleccione un pipeline...</option>
                                @if(($data['kommo_pipeline_default_status_id'] ?? '') !== '' && !collect($defaultKommoStatuses)->contains('id', (string) $data['kommo_pipeline_default_status_id']))
                                    <option value="{{ $data['kommo_pipeline_default_status_id'] }}">{{ $data['kommo_pipeline_default_status_name'] ?: $data['kommo_pipeline_default_status_id'] }}</option>
                                @endif
                                @foreach($defaultKommoStatuses as $status)
                                    <option value="{{ $status['id'] }}">{{ $status['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="border-t border-white/10 pt-5">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h4 class="font-semibold text-white">Condicionalidades</h4>
                                <p class="text-sm text-white/50">Selecciona pipeline y estado segun un campo del lead.</p>
                            </div>
                            <button type="button" wire:click="addKommoCondition" class="rounded-lg border border-white/10 bg-indigo-500/30 px-3 py-2 text-white hover:bg-indigo-500/40">Agregar condicion</button>
                        </div>

                        <div class="space-y-3">
                            @forelse($kommoConditions as $index => $condition)
                                <div wire:key="kommo-condition-{{ $condition['_key'] }}" class="grid grid-cols-1 gap-3 border border-white/10 bg-white/5 p-3 lg:grid-cols-6">
                                    <div>
                                        <label class="{{ $labelClass }}">Campo Lead</label>
                                        <select name="kommo_pipeline_conditions[{{ $index }}][lead_field]" wire:model.change="kommoConditions.{{ $index }}.lead_field" class="{{ $inputClass }}" required>
                                            <option value="">Seleccione...</option>
                                            @foreach($leadFields as $field)<option value="{{ $field }}">{{ $field }}</option>@endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="{{ $labelClass }}">Valor esperado</label>
                                        <input name="kommo_pipeline_conditions[{{ $index }}][expected_value]" wire:model.blur="kommoConditions.{{ $index }}.expected_value" class="{{ $inputClass }}" required>
                                    </div>
                                    <div>
                                        <label class="{{ $labelClass }}">Pipeline</label>
                                        <input type="hidden" name="kommo_pipeline_conditions[{{ $index }}][pipeline_name]" value="{{ $condition['pipeline_name'] ?? '' }}">
                                        <select name="kommo_pipeline_conditions[{{ $index }}][pipeline_id]" wire:model="kommoConditions.{{ $index }}.pipeline_id" wire:change="loadConditionKommoStatuses({{ $index }})" class="{{ $inputClass }}" required>
                                            <option value="">Seleccione...</option>
                                            @if(($condition['pipeline_id'] ?? '') !== '' && !collect($kommoPipelines)->contains('id', (string) $condition['pipeline_id']))
                                                <option value="{{ $condition['pipeline_id'] }}">{{ $condition['pipeline_name'] ?: $condition['pipeline_id'] }}</option>
                                            @endif
                                            @foreach($kommoPipelines as $pipeline)<option value="{{ $pipeline['id'] }}">{{ $pipeline['name'] }}</option>@endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="{{ $labelClass }}">Status</label>
                                        <input type="hidden" name="kommo_pipeline_conditions[{{ $index }}][status_name]" value="{{ $condition['status_name'] ?? '' }}">
                                        <select name="kommo_pipeline_conditions[{{ $index }}][status_id]" wire:model="kommoConditions.{{ $index }}.status_id" wire:change="setConditionKommoStatusName({{ $index }})" class="{{ $inputClass }}" required>
                                            <option value="">Seleccione...</option>
                                            @if(($condition['status_id'] ?? '') !== '' && !collect($conditionKommoStatuses[$index] ?? [])->contains('id', (string) $condition['status_id']))
                                                <option value="{{ $condition['status_id'] }}">{{ $condition['status_name'] ?: $condition['status_id'] }}</option>
                                            @endif
                                            @foreach($conditionKommoStatuses[$index] ?? [] as $status)<option value="{{ $status['id'] }}">{{ $status['name'] }}</option>@endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="{{ $labelClass }}">Activa</label>
                                        <input type="hidden" name="kommo_pipeline_conditions[{{ $index }}][active]" value="0">
                                        <x-toggle-switch name="kommo_pipeline_conditions[{{ $index }}][active]" value="1" wire:model.change="kommoConditions.{{ $index }}.active" label="Sí" />
                                    </div>
                                    <div class="flex items-end justify-end">
                                        <input type="hidden" name="kommo_pipeline_conditions[{{ $index }}][order]" value="{{ $index }}">
                                        <button type="button" wire:click="removeKommoCondition({{ $index }})" class="rounded-lg border border-rose-300/20 bg-rose-500/20 px-3 py-2 text-white hover:bg-rose-500/30">Quitar</button>
                                    </div>
                                </div>
                            @empty
                                <p class="border border-dashed border-white/15 p-5 text-center text-sm text-white/50">No hay condiciones. Se usara el pipeline por defecto.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
                @break

            @case('atom')
                <div class="space-y-5">
                    <div>
                        <label class="{{ $labelClass }}">Token de autenticacion *</label>
                        <input name="tokent" type="password" wire:model="typeData.atom.tokent" class="{{ $inputClass }}"
                               autocomplete="new-password" placeholder="{{ $tokenStored ? 'Token guardado; deja vacio para conservarlo' : 'Bearer token' }}"
                               @required(!$tokenStored)>
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Body JSON *</label>
                        <textarea name="body" rows="12" wire:model.blur="typeData.atom.body" class="{{ $inputClass }} font-mono text-sm" required></textarea>
                    </div>
                    @include('livewire.integrations.partials.webhooks', ['provider' => 'atom'])
                </div>
                @break

            @case('lety')
                @include('livewire.integrations.partials.webhooks', ['provider' => 'lety'])
                @break

            @case('zoho')
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div><label class="{{ $labelClass }}">client_id</label><input name="client_id" wire:model.blur="typeData.zoho.client_id" class="{{ $inputClass }}"></div>
                    <div><label class="{{ $labelClass }}">client_secret</label><input name="client_secret" type="password" wire:model="typeData.zoho.client_secret" autocomplete="new-password" class="{{ $inputClass }}" placeholder="{{ $clientSecretStored ? 'Valor guardado; deja vacio para conservarlo' : '' }}"></div>
                    <div><label class="{{ $labelClass }}">code</label><input name="code" wire:model.blur="typeData.zoho.code" class="{{ $inputClass }}"></div>
                    <div><label class="{{ $labelClass }}">access_token</label><input name="access_token" type="password" wire:model="typeData.zoho.access_token" autocomplete="new-password" class="{{ $inputClass }}" placeholder="{{ $accessTokenStored ? 'Valor guardado; deja vacio para conservarlo' : '' }}"></div>
                    <div><label class="{{ $labelClass }}">refresh_token</label><input name="refresh_token" type="password" wire:model="typeData.zoho.refresh_token" autocomplete="new-password" class="{{ $inputClass }}" placeholder="{{ $refreshTokenStored ? 'Valor guardado; deja vacio para conservarlo' : '' }}"></div>
                </div>
                @break

            @case('freshworks')
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="md:col-span-2"><label class="{{ $labelClass }}">Token *</label><input name="tokent" type="password" wire:model="typeData.freshworks.tokent" autocomplete="new-password" class="{{ $inputClass }}" placeholder="{{ $tokenStored ? 'Token guardado; deja vacio para conservarlo' : '' }}" @required(!$tokenStored)></div>
                    @foreach(['territory_id', 'owner_id', 'city', 'lead_source_id'] as $field)
                        <div><label class="{{ $labelClass }}">{{ $field }} *</label><input name="{{ $field }}" wire:model.blur="typeData.freshworks.{{ $field }}" class="{{ $inputClass }}" required></div>
                    @endforeach
                    <div class="md:col-span-2"><label class="{{ $labelClass }}">custom_field *</label><textarea name="custom_field" rows="8" wire:model.blur="typeData.freshworks.custom_field" class="{{ $inputClass }} font-mono text-sm" required></textarea></div>
                    <div class="md:col-span-2">@include('livewire.integrations.partials.mappings', ['items' => $freshworksMappings, 'scope' => 'freshworks'])</div>
                </div>
                @break

            @case('freshworks_oportunidad')
                <div class="space-y-4">
                    <div><label class="{{ $labelClass }}">Token Freshworks *</label><input name="tokent" type="password" wire:model="typeData.freshworks_oportunidad.tokent" autocomplete="new-password" class="{{ $inputClass }}" placeholder="{{ $tokenStored ? 'Token guardado; deja vacio para conservarlo' : '' }}" @required(!$tokenStored)></div>
                    <div><label class="{{ $labelClass }}">Body JSON contacto *</label><textarea name="body" rows="10" wire:model.blur="typeData.freshworks_oportunidad.body" class="{{ $inputClass }} font-mono text-sm" required placeholder='{"first_name":"@{{lead->name}}","last_name":"@{{lead->last_name}}","mobile_number":"@{{lead->phone}}"}'></textarea><p class="mt-1 text-xs text-white/50">mobile_number se toma siempre del teléfono válido del lead.</p></div>
                    <div><label class="{{ $labelClass }}">Body JSON oportunidad *</label><textarea name="body_oportunidad" rows="12" wire:model.blur="typeData.freshworks_oportunidad.body_oportunidad" class="{{ $inputClass }} font-mono text-sm" required placeholder='{"name":"@{{lead->name}}","amount":"@{{lead->value}}","deal_pipeline_id":0,"deal_stage_id":0,"probability":10}'></textarea><p class="mt-1 text-xs text-white/50">Se asocia automáticamente el contacto creado o actualizado.</p></div>
                </div>
                @break

            @case('zapnito_invitacion')
                <div class="space-y-4">
                    <div><label class="{{ $labelClass }}">Token Zapnito *</label><input name="tokent" type="password" wire:model="typeData.zapnito_invitacion.tokent" autocomplete="new-password" class="{{ $inputClass }}" placeholder="{{ $tokenStored ? 'Token guardado; deja vacio para conservarlo' : '' }}" @required(!$tokenStored)></div>
                    <div><label class="{{ $labelClass }}">Body JSON invitation *</label><textarea name="body" rows="12" wire:model.blur="typeData.zapnito_invitacion.body" class="{{ $inputClass }} font-mono text-sm" required></textarea></div>
                </div>
                @break

            @case('gohighlevel')
            @case('gohighlevel_oportunidad')
                <div class="space-y-5 rounded-2xl border border-white/10 bg-white/5 p-5 text-white/80">
                    <div class="rounded-xl border border-white/10 bg-slate-900/40 p-4"><label class="{{ $labelClass }}">Token LeadConnector / GoHighLevel *</label><input name="tokent" type="password" wire:model.live.blur="typeData.{{ $typeKey }}.tokent" autocomplete="new-password" class="{{ $inputClass }}" placeholder="{{ $tokenStored ? 'Token guardado; deja vacio para conservarlo' : '' }}" @required(!$tokenStored)></div>
                    @if($typeKey === 'gohighlevel_oportunidad')
                        <div class="rounded-xl border border-white/10 bg-slate-900/40 p-4"><label class="{{ $labelClass }}">locationId</label><input name="location_id" wire:model.live.blur="typeData.gohighlevel_oportunidad.location_id" maxlength="100" autocomplete="off" class="{{ $inputClass }}" placeholder="ID de la subcuenta de GoHighLevel"></div>
                    @endif
                    @if($typeKey === 'gohighlevel_oportunidad' && $tokenStored)
                        <livewire:gohighlevel-pipeline-catalog
                            :integration-id="$integrationId"
                            :location-id="(string) ($data['location_id'] ?? '')"
                            :input-class="$inputClass"
                            :label-class="$labelClass"
                            :key="'gohighlevel-catalog-'.$integrationId.'-'.md5((string) ($data['location_id'] ?? ''))"
                        />
                    @endif
                    <div class="rounded-xl border border-white/10 bg-slate-900/40 p-4"><label class="{{ $labelClass }}">Body JSON *</label><textarea name="body" rows="12" wire:model.blur="typeData.{{ $typeKey }}.body" class="{{ $inputClass }} min-h-56 font-mono text-sm leading-6" required></textarea></div>
                    @if($typeKey === 'gohighlevel_oportunidad')
                        <div class="rounded-xl border border-white/10 bg-slate-900/40 p-4"><label class="{{ $labelClass }}">Body oportunidad *</label><textarea name="body_oportunidad" rows="12" wire:model.blur="typeData.gohighlevel_oportunidad.body_oportunidad" class="{{ $inputClass }} min-h-56 font-mono text-sm leading-6" required></textarea></div>
                        <div class="rounded-xl border border-sky-300/20 bg-sky-500/10 p-4 shadow-sm shadow-sky-950/30">
                            <input type="hidden" name="omit_empty_payload_fields" value="0">
                            <x-toggle-switch name="omit_empty_payload_fields" value="1" wire:model.change="typeData.gohighlevel_oportunidad.omit_empty_payload_fields" label="Omitir atributos vacíos o nulos del body" :checked="(bool) ($data['omit_empty_payload_fields'] ?? true)">
                                <span>
                                    <span class="block text-xs text-sky-100/70">Evita enviar, por ejemplo, <code class="font-mono">&quot;email&quot;: &quot;&quot;</code> a GoHighLevel. Conserva <code class="font-mono">0</code>, <code class="font-mono">false</code> y los campos requeridos configurados.</span>
                                </span>
                            </x-toggle-switch>
                        </div>
                    @endif
                </div>
                @break

            @case('salesforce')
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div><label class="{{ $labelClass }}">URL credenciales *</label><input name="url_credenciales" type="url" wire:model.blur="typeData.salesforce.url_credenciales" class="{{ $inputClass }}" required></div>
                    <div><label class="{{ $labelClass }}">Client ID / Consumer Key *</label><input name="username" wire:model.blur="typeData.salesforce.username" class="{{ $inputClass }}" required></div>
                    <div><label class="{{ $labelClass }}">Client Secret *</label><input name="password" type="password" wire:model="typeData.salesforce.password" autocomplete="new-password" class="{{ $inputClass }}" placeholder="{{ $passwordStored ? 'Valor guardado; deja vacio para conservarlo' : '' }}" @required(!$passwordStored)></div>
                    <div><label class="{{ $labelClass }}">Token generado</label><input name="tokent" type="password" value="" readonly class="{{ $inputClass }} opacity-60" placeholder="Se actualiza al autenticar"></div>
                    <div class="md:col-span-2"><label class="{{ $labelClass }}">Body JSON *</label><textarea name="body" rows="10" wire:model.blur="typeData.salesforce.body" class="{{ $inputClass }} font-mono text-sm" required></textarea></div>
                </div>
                @break

            @case('monday')
                <div><label class="{{ $labelClass }}">Authorization *</label><input name="tokent" type="password" wire:model="typeData.monday.tokent" autocomplete="new-password" class="{{ $inputClass }}" placeholder="{{ $tokenStored ? 'Token guardado; deja vacio para conservarlo' : '' }}" @required(!$tokenStored)></div>
                @break

            @case('hubspot')
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="md:col-span-2"><label class="{{ $labelClass }}">access_token *</label><input name="access_token" type="password" wire:model="typeData.hubspot.access_token" autocomplete="new-password" class="{{ $inputClass }}" placeholder="{{ $accessTokenStored ? 'Valor guardado; deja vacio para conservarlo' : '' }}" @required(!$accessTokenStored)></div>
                    <div><label class="{{ $labelClass }}">Body contacto *</label><textarea name="body" rows="10" wire:model.blur="typeData.hubspot.body" class="{{ $inputClass }} font-mono text-sm" required></textarea></div>
                    <div><label class="{{ $labelClass }}">Body oportunidad *</label><textarea name="body_oportunidad" rows="10" wire:model.blur="typeData.hubspot.body_oportunidad" class="{{ $inputClass }} font-mono text-sm" required></textarea></div>
                </div>
                @break
        @endswitch

        @foreach(['tokent', 'body', 'body_oportunidad', 'client_id', 'client_secret', 'refresh_token', 'password', 'custom_field'] as $errorField)
            @error($errorField) <p class="text-sm text-rose-300">{{ $message }}</p> @enderror
        @endforeach
    </section>
@endif
