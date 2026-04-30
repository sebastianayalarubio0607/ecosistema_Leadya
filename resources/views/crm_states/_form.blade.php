@php
    /** @var \App\Models\CrmState|null $crmstate */
    $crmstate = $crmstate ?? null;
    $isCreate = $isCreate ?? false;
    $selectedIntegrationId = old('integration_id');
    $selectedIntegration = $isCreate && isset($integrations)
        ? $integrations->firstWhere('id', (int) $selectedIntegrationId)
        : null;
    $googleAdsCustomerId = ($integration ?? null)?->customer?->id
        ?? $selectedIntegration?->customer?->id
        ?? null;
@endphp

<div class="space-y-4" data-google-ads-crm-form>
    @if($isCreate)
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
            <div class="md:col-span-7">
                <label class="block mb-1 text-white/70">Integracion</label>
                <select name="integration_id"
                        data-google-ads-integration
                        class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">-- Selecciona --</option>
                    @foreach($integrations as $in)
                        <option value="{{ $in->id }}"
                                data-customer-id="{{ $in->customer?->id }}"
                                data-google-ads-customer-id="{{ $in->customer?->id_Gads }}"
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
                    <div class="text-xs text-white/50">Integration ID</div>
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

        <div>
            <label class="block mb-1 text-white/70">Conversion de Google Ads</label>
            <select data-google-ads-actions
                    data-current-id="{{ old('google_ads_conversion_action_id', $crmstate?->google_ads_conversion_action_id) }}"
                    data-current-name="{{ old('google_ads_conversion_action_name', $crmstate?->google_ads_conversion_action_name) }}"
                    data-current-resource="{{ old('google_ads_conversion_action_resource_name', $crmstate?->google_ads_conversion_action_resource_name) }}"
                    data-customer-id="{{ $googleAdsCustomerId }}"
                    class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                <option value="">-- Sin asignar --</option>
                @if(old('google_ads_conversion_action_id', $crmstate?->google_ads_conversion_action_id))
                    <option value="{{ old('google_ads_conversion_action_id', $crmstate?->google_ads_conversion_action_id) }}"
                            data-name="{{ old('google_ads_conversion_action_name', $crmstate?->google_ads_conversion_action_name) }}"
                            data-resource="{{ old('google_ads_conversion_action_resource_name', $crmstate?->google_ads_conversion_action_resource_name) }}"
                            selected>
                        {{ old('google_ads_conversion_action_name', $crmstate?->google_ads_conversion_action_name) ?: old('google_ads_conversion_action_id', $crmstate?->google_ads_conversion_action_id) }}
                    </option>
                @endif
            </select>
            <input type="hidden" name="google_ads_conversion_action_id" value="{{ old('google_ads_conversion_action_id', $crmstate?->google_ads_conversion_action_id) }}" data-google-ads-action-id>
            <input type="hidden" name="google_ads_conversion_action_name" value="{{ old('google_ads_conversion_action_name', $crmstate?->google_ads_conversion_action_name) }}" data-google-ads-action-name>
            <input type="hidden" name="google_ads_conversion_action_resource_name" value="{{ old('google_ads_conversion_action_resource_name', $crmstate?->google_ads_conversion_action_resource_name) }}" data-google-ads-action-resource>
            <div class="text-xs text-white/50 mt-1" data-google-ads-actions-status>
                Solo se listan conversiones UPLOAD_CLICKS habilitadas.
            </div>
            @error('google_ads_conversion_action_id') <div class="text-rose-300 text-xs mt-1">{{ $message }}</div> @enderror
            @error('google_ads_conversion_action_resource_name') <div class="text-rose-300 text-xs mt-1">{{ $message }}</div> @enderror
        </div>

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
</div>

@once
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-google-ads-crm-form]').forEach((form) => {
                const integration = form.querySelector('[data-google-ads-integration]');
                const select = form.querySelector('[data-google-ads-actions]');
                const status = form.querySelector('[data-google-ads-actions-status]');
                const idInput = form.querySelector('[data-google-ads-action-id]');
                const nameInput = form.querySelector('[data-google-ads-action-name]');
                const resourceInput = form.querySelector('[data-google-ads-action-resource]');

                if (!select) {
                    return;
                }

                const setSelectedFields = () => {
                    const option = select.selectedOptions[0];
                    idInput.value = option?.value || '';
                    nameInput.value = option?.dataset.name || '';
                    resourceInput.value = option?.dataset.resource || '';
                };

                const loadActions = async (customerId, keepId = null) => {
                    select.innerHTML = '<option value="">Cargando...</option>';
                    idInput.value = '';
                    nameInput.value = '';
                    resourceInput.value = '';

                    if (!customerId) {
                        select.innerHTML = '<option value="">Sin customer id_Gads</option>';
                        status.textContent = 'Selecciona una integracion con customer y id_Gads configurado.';
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

                        setSelectedFields();
                    } catch (error) {
                        select.innerHTML = '<option value="">No disponible</option>';
                        status.textContent = 'No fue posible consultar Google Ads.';
                    }
                };

                select.addEventListener('change', setSelectedFields);

                if (integration) {
                    integration.addEventListener('change', () => {
                        const option = integration.selectedOptions[0];
                        loadActions(option?.dataset.customerId || null);
                    });
                }

                const initialCustomerId = select.dataset.customerId;
                const currentId = select.dataset.currentId;

                if (initialCustomerId) {
                    loadActions(initialCustomerId, currentId);
                }
            });
        });
    </script>
@endonce
