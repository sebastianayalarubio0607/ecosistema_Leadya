@php
    /** @var \App\Models\CrmState|null $crmstate */
    $crmstate = $crmstate ?? null;
    $isCreate = $isCreate ?? false;
    $selectedIntegrationId = old('integration_id');
    $selectedIntegration = $isCreate && isset($integrations)
        ? $integrations->firstWhere('id', (int) $selectedIntegrationId)
        : null;

    $storedGoogleAdsRows = $crmstate?->googleAdsConversions
        ? $crmstate->googleAdsConversions->map(fn ($item) => [
            'customer_id' => $item->customer_id,
            'conversion_action_id' => $item->conversion_action_id,
            'conversion_action_name' => $item->conversion_action_name,
            'conversion_action_resource_name' => $item->conversion_action_resource_name,
        ])->values()->all()
        : [];

    if (!$isCreate && empty($storedGoogleAdsRows) && $crmstate?->google_ads_conversion_action_id) {
        $storedGoogleAdsRows[] = [
            'customer_id' => $integration?->customer_id,
            'conversion_action_id' => $crmstate->google_ads_conversion_action_id,
            'conversion_action_name' => $crmstate->google_ads_conversion_action_name,
            'conversion_action_resource_name' => $crmstate->google_ads_conversion_action_resource_name,
        ];
    }

    $googleAdsRows = old('google_ads_conversions', $storedGoogleAdsRows);
@endphp

<div class="space-y-4" data-google-ads-crm-form>
    @if($isCreate)
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
            <div class="md:col-span-7">
                <label class="block mb-1 text-white/70">Integracion</label>
                <select name="integration_id"
                        class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">-- Selecciona --</option>
                    @foreach($integrations as $in)
                        <option value="{{ $in->id }}"
                                @selected(old('integration_id') == $in->id)>
                            {{ $in->customer?->name ?? '--' }} - {{ $in->name }} ({{ $in->id }})
                        </option>
                    @endforeach
                </select>
                @error('integration_id') <div class="text-rose-300 text-xs mt-1">{{ $message }}</div> @enderror
            </div>

            <div class="md:col-span-5">
                <label class="block mb-1 text-white/70">External ID (del CRM)</label>
                <input name="external_id" value="{{ old('external_id') }}"
                       class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
                       placeholder="Ej: 123, WON, status_1">
                @error('external_id') <div class="text-rose-300 text-xs mt-1">{{ $message }}</div> @enderror
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
            <div class="md:col-span-6">
                <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                    <div class="text-xs text-white/50">Integration ID / Prefijo</div>
                    <div class="text-white">{{ $integrationId ?? '--' }}</div>
                    <div class="text-xs text-white/50 mt-2">Customer / Integration</div>
                    <div class="text-white">
                        {{ $integration?->customer?->name ?? '--' }} - {{ $integration?->name ?? '--' }}
                    </div>
                </div>
            </div>

            <div class="md:col-span-6">
                <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                    <div class="text-xs text-white/50">External ID</div>
                    <div class="text-white">{{ $externalId ?? '--' }}</div>
                    <div class="text-xs text-white/50 mt-2">CRM State ID</div>
                    <div class="text-white">{{ $crmstate?->id ?? '--' }}</div>
                </div>
            </div>
        </div>
    @endif

    <div>
        <label class="block mb-1 text-white/70">Nombre</label>
        <input name="name" value="{{ old('name', $crmstate?->name) }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
               placeholder="Ej: Nuevo, Contactado, Ganado, Perdido">
        @error('name') <div class="text-rose-300 text-xs mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
        <div class="md:col-span-6">
            <label class="block mb-1 text-white/70">Qualification</label>
            <select name="qualification"
                    class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                <option value="">-- Selecciona --</option>
                @foreach($qualifications as $q)
                    <option value="{{ $q->id }}" @selected(old('qualification', $crmstate?->qualification) == $q->id)>
                        {{ $q->name }}
                    </option>
                @endforeach
            </select>
            @error('qualification') <div class="text-rose-300 text-xs mt-1">{{ $message }}</div> @enderror
        </div>

        <div class="md:col-span-6">
            <label class="block mb-1 text-white/70">Meta Event (Conversion)</label>
            <select name="meta_event_id"
                    class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                <option value="">-- Sin asignar --</option>
                @foreach($metaEvents as $me)
                    <option value="{{ $me->id }}" @selected(old('meta_event_id', $crmstate?->meta_event_id) == $me->id)>
                        {{ $me->nombre }} ({{ $me->estados }})
                    </option>
                @endforeach
            </select>
            @error('meta_event_id') <div class="text-rose-300 text-xs mt-1">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="rounded-xl border border-white/10 bg-white/5 p-4 space-y-3">
        <div class="flex items-center justify-between gap-3">
            <label class="flex items-center gap-2 text-white/80">
                <input type="hidden" name="google_ads_conversion_enabled" value="0">
                <input type="checkbox"
                       name="google_ads_conversion_enabled"
                       value="1"
                       @checked(old('google_ads_conversion_enabled', $crmstate?->google_ads_conversion_enabled) == 1)
                       class="rounded border-white/10 bg-slate-900/60 text-indigo-500">
                Enviar conversion a Google Ads
            </label>

            <a href="{{ route('google-ads.conversion-jobs.index') }}"
               class="text-xs text-indigo-200 hover:text-white">
                Ver jobs
            </a>
        </div>
        @error('google_ads_conversion_enabled') <div class="text-rose-300 text-xs mt-1">{{ $message }}</div> @enderror
        @error('google_ads_conversions') <div class="text-rose-300 text-xs mt-1">{{ $message }}</div> @enderror

        <div class="space-y-3" data-google-ads-matrix>
            @forelse($googleAdsRows as $index => $row)
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-start rounded-xl border border-white/10 bg-slate-950/40 p-3"
                     data-google-ads-row>
                    <div class="md:col-span-4">
                        <label class="block mb-1 text-white/70">Customer</label>
                        <select name="google_ads_conversions[{{ $index }}][customer_id]"
                                data-google-ads-customer
                                class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                            <option value="">-- Selecciona --</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" @selected((string) data_get($row, 'customer_id') === (string) $customer->id)>
                                    {{ $customer->name }} ({{ $customer->id_Gads }})
                                </option>
                            @endforeach
                        </select>
                        @error("google_ads_conversions.{$index}.customer_id") <div class="text-rose-300 text-xs mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="md:col-span-7">
                        <label class="block mb-1 text-white/70">Conversion de Google Ads</label>
                        <select data-google-ads-actions
                                data-current-id="{{ data_get($row, 'conversion_action_id') }}"
                                data-current-name="{{ data_get($row, 'conversion_action_name') }}"
                                data-current-resource="{{ data_get($row, 'conversion_action_resource_name') }}"
                                class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                            <option value="">-- Sin asignar --</option>
                            @if(data_get($row, 'conversion_action_id'))
                                <option value="{{ data_get($row, 'conversion_action_id') }}"
                                        data-name="{{ data_get($row, 'conversion_action_name') }}"
                                        data-resource="{{ data_get($row, 'conversion_action_resource_name') }}"
                                        selected>
                                    {{ data_get($row, 'conversion_action_name') ?: data_get($row, 'conversion_action_id') }}
                                </option>
                            @endif
                        </select>
                        <input type="hidden" name="google_ads_conversions[{{ $index }}][conversion_action_id]" value="{{ data_get($row, 'conversion_action_id') }}" data-google-ads-action-id>
                        <input type="hidden" name="google_ads_conversions[{{ $index }}][conversion_action_name]" value="{{ data_get($row, 'conversion_action_name') }}" data-google-ads-action-name>
                        <input type="hidden" name="google_ads_conversions[{{ $index }}][conversion_action_resource_name]" value="{{ data_get($row, 'conversion_action_resource_name') }}" data-google-ads-action-resource>
                        <div class="text-xs text-white/50 mt-1" data-google-ads-actions-status>
                            Selecciona un customer para cargar conversiones UPLOAD_CLICKS habilitadas.
                        </div>
                    </div>

                    <div class="md:col-span-1 pt-6">
                        <button type="button"
                                class="w-full px-3 py-2 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 text-white border border-rose-300/20"
                                data-google-ads-remove>
                            X
                        </button>
                    </div>
                </div>
            @empty
            @endforelse
        </div>

        <button type="button"
                class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10"
                data-google-ads-add>
            + Agregar customer
        </button>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
            <div class="md:col-span-6">
                <label class="block mb-1 text-white/70">Valor de conversion</label>
                <input type="number"
                       step="0.01"
                       min="0"
                       name="google_ads_conversion_value"
                       value="{{ old('google_ads_conversion_value', $crmstate?->google_ads_conversion_value) }}"
                       class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
                       placeholder="0.5">
                @error('google_ads_conversion_value') <div class="text-rose-300 text-xs mt-1">{{ $message }}</div> @enderror
            </div>

            <div class="md:col-span-6">
                <label class="block mb-1 text-white/70">Moneda</label>
                <input name="google_ads_conversion_currency"
                       maxlength="3"
                       value="{{ old('google_ads_conversion_currency', $crmstate?->google_ads_conversion_currency ?? 'COP') }}"
                       class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40">
                @error('google_ads_conversion_currency') <div class="text-rose-300 text-xs mt-1">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>

    <label class="flex items-center gap-2 text-white/80">
        <input type="hidden" name="unmanaged" value="0">
        <input type="checkbox" name="unmanaged" value="1"
               @checked(old('unmanaged', $crmstate?->unmanaged) == 1)
               class="rounded border-white/10 bg-slate-900/60 text-indigo-500">
        Sin gestionar
    </label>
    @error('unmanaged') <div class="text-rose-300 text-xs mt-1">{{ $message }}</div> @enderror

    <div class="flex gap-2">
        <button class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">
            {{ $submitText ?? 'Guardar' }}
        </button>

        <a href="{{ $cancelUrl ?? route('crmstates.index') }}"
           class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
            Cancelar
        </a>
    </div>

    <template data-google-ads-row-template>
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-start rounded-xl border border-white/10 bg-slate-950/40 p-3"
             data-google-ads-row>
            <div class="md:col-span-4">
                <label class="block mb-1 text-white/70">Customer</label>
                <select name="google_ads_conversions[__INDEX__][customer_id]"
                        data-google-ads-customer
                        class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">-- Selecciona --</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->name }} ({{ $customer->id_Gads }})</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-7">
                <label class="block mb-1 text-white/70">Conversion de Google Ads</label>
                <select data-google-ads-actions
                        class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">-- Sin asignar --</option>
                </select>
                <input type="hidden" name="google_ads_conversions[__INDEX__][conversion_action_id]" data-google-ads-action-id>
                <input type="hidden" name="google_ads_conversions[__INDEX__][conversion_action_name]" data-google-ads-action-name>
                <input type="hidden" name="google_ads_conversions[__INDEX__][conversion_action_resource_name]" data-google-ads-action-resource>
                <div class="text-xs text-white/50 mt-1" data-google-ads-actions-status>
                    Selecciona un customer para cargar conversiones UPLOAD_CLICKS habilitadas.
                </div>
            </div>

            <div class="md:col-span-1 pt-6">
                <button type="button"
                        class="w-full px-3 py-2 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 text-white border border-rose-300/20"
                        data-google-ads-remove>
                    X
                </button>
            </div>
        </div>
    </template>
</div>

@once
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-google-ads-crm-form]').forEach((form) => {
                const matrix = form.querySelector('[data-google-ads-matrix]');
                const template = form.querySelector('[data-google-ads-row-template]');
                const addButton = form.querySelector('[data-google-ads-add]');

                if (!matrix || !template || !addButton) {
                    return;
                }

                const setSelectedFields = (row) => {
                    const select = row.querySelector('[data-google-ads-actions]');
                    const idInput = row.querySelector('[data-google-ads-action-id]');
                    const nameInput = row.querySelector('[data-google-ads-action-name]');
                    const resourceInput = row.querySelector('[data-google-ads-action-resource]');
                    const option = select.selectedOptions[0];

                    idInput.value = option?.value || '';
                    nameInput.value = option?.dataset.name || '';
                    resourceInput.value = option?.dataset.resource || '';
                };

                const loadActions = async (row, customerId, keepId = null) => {
                    const select = row.querySelector('[data-google-ads-actions]');
                    const status = row.querySelector('[data-google-ads-actions-status]');
                    const idInput = row.querySelector('[data-google-ads-action-id]');
                    const nameInput = row.querySelector('[data-google-ads-action-name]');
                    const resourceInput = row.querySelector('[data-google-ads-action-resource]');

                    select.innerHTML = '<option value="">Cargando...</option>';
                    idInput.value = '';
                    nameInput.value = '';
                    resourceInput.value = '';

                    if (!customerId) {
                        select.innerHTML = '<option value="">-- Sin asignar --</option>';
                        status.textContent = 'Selecciona un customer para cargar conversiones.';
                        return;
                    }

                    try {
                        const url = new URL(@json(route('google-ads.conversion-actions.index')), window.location.origin);
                        url.searchParams.set('customer_id', customerId);
                        const response = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                        const data = await response.json();

                        select.innerHTML = '<option value="">-- Sin asignar --</option>';

                        (data.actions || []).forEach((action) => {
                            const option = document.createElement('option');
                            option.value = action.id;
                            option.dataset.name = action.name;
                            option.dataset.resource = action.resource_name;
                            option.textContent = `${action.name} (${action.id})`;
                            option.selected = keepId && String(keepId) === String(action.id);
                            select.appendChild(option);
                        });

                        status.textContent = data.success
                            ? `${(data.actions || []).length} conversiones disponibles.`
                            : (data.error_message || 'No fue posible cargar conversiones.');

                        setSelectedFields(row);
                    } catch (error) {
                        select.innerHTML = '<option value="">No disponible</option>';
                        status.textContent = 'No fue posible consultar Google Ads.';
                    }
                };

                const bindRow = (row) => {
                    const customer = row.querySelector('[data-google-ads-customer]');
                    const select = row.querySelector('[data-google-ads-actions]');
                    const remove = row.querySelector('[data-google-ads-remove]');

                    customer.addEventListener('change', () => loadActions(row, customer.value));
                    select.addEventListener('change', () => setSelectedFields(row));
                    remove.addEventListener('click', () => row.remove());

                    if (customer.value) {
                        loadActions(row, customer.value, select.dataset.currentId || null);
                    }
                };

                addButton.addEventListener('click', () => {
                    const index = Date.now().toString();
                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', index).trim();
                    const row = wrapper.firstElementChild;
                    matrix.appendChild(row);
                    bindRow(row);
                });

                matrix.querySelectorAll('[data-google-ads-row]').forEach(bindRow);

                if (!matrix.querySelector('[data-google-ads-row]')) {
                    addButton.click();
                }
            });
        });
    </script>
@endonce
