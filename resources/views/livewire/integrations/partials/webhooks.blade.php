@php
    $isAtom = $provider === 'atom';
    $webhooks = $isAtom ? $atomWebhooks : $letyWebhooks;
    $conditions = $isAtom ? $atomConditions : $letyConditions;
    $webhookField = $isAtom ? 'atom_webhooks' : 'lety_webhooks';
    $conditionField = $isAtom ? 'atom_conditions' : 'lety_conditions';
    $webhookState = $isAtom ? 'atomWebhooks' : 'letyWebhooks';
    $conditionState = $isAtom ? 'atomConditions' : 'letyConditions';
    $addWebhook = $isAtom ? 'addAtomWebhook' : 'addLetyWebhook';
    $removeWebhook = $isAtom ? 'removeAtomWebhook' : 'removeLetyWebhook';
    $addCondition = $isAtom ? 'addAtomCondition' : 'addLetyCondition';
    $removeCondition = $isAtom ? 'removeAtomCondition' : 'removeLetyCondition';
@endphp

<div class="space-y-5 rounded-2xl border border-white/10 bg-white/5 p-5 shadow-sm shadow-black/10 sm:p-6">
    <div>
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h4 class="font-semibold text-white">Webhooks {{ ucfirst($provider) }}</h4>
                <p class="text-sm text-white/50">Configura los endpoints disponibles para esta integracion.</p>
            </div>
            <button type="button" wire:click="{{ $addWebhook }}" class="rounded-xl border border-indigo-300/20 bg-indigo-500/30 px-3 py-2 text-white transition hover:bg-indigo-500/40">Agregar webhook</button>
        </div>

        <div class="space-y-3">
            @foreach($webhooks as $index => $webhook)
                <div wire:key="{{ $provider }}-webhook-{{ $webhook['_key'] }}" class="grid grid-cols-1 gap-3 rounded-xl border border-white/10 bg-slate-900/40 p-4 {{ $isAtom ? 'lg:grid-cols-5' : 'lg:grid-cols-6' }}">
                    <input type="hidden" name="{{ $webhookField }}[{{ $index }}][key]" value="{{ $webhook['key'] }}">
                    <input type="hidden" name="{{ $webhookField }}[{{ $index }}][order]" value="{{ $index }}">
                    <div>
                        <label class="{{ $labelClass }}">Nombre</label>
                        <input name="{{ $webhookField }}[{{ $index }}][name]" wire:model.blur="{{ $webhookState }}.{{ $index }}.name" class="{{ $inputClass }}" required>
                    </div>
                    <div class="{{ $isAtom ? 'lg:col-span-2' : 'lg:col-span-2' }}">
                        <label class="{{ $labelClass }}">URL</label>
                        <input name="{{ $webhookField }}[{{ $index }}][url]" type="url" wire:model.blur="{{ $webhookState }}.{{ $index }}.url" class="{{ $inputClass }}" required>
                    </div>
                    @unless($isAtom)
                        <div class="lg:col-span-2">
                            <label class="{{ $labelClass }}">Payload form-urlencoded</label>
                            <textarea name="lety_webhooks[{{ $index }}][body]" wire:model.blur="letyWebhooks.{{ $index }}.body" rows="5" class="{{ $inputClass }} font-mono text-xs" required></textarea>
                        </div>
                    @endunless
                    @if($isAtom)
                        <div>
                            <label class="{{ $labelClass }}">Predeterminado</label>
                            <input type="hidden" name="atom_webhooks[{{ $index }}][is_default]" value="{{ $atomDefaultKey === $webhook['_key'] ? '1' : '0' }}">
                            <label class="inline-flex items-center gap-2 pt-2 text-sm text-white/80">
                                <input type="radio" wire:model.live="atomDefaultKey" value="{{ $webhook['_key'] }}" class="border-white/20 bg-slate-900 text-indigo-500"> Si
                            </label>
                        </div>
                    @endif
                    <div class="flex items-end justify-between gap-3">
                        <div>
                            <input type="hidden" name="{{ $webhookField }}[{{ $index }}][active]" value="0">
                            <x-toggle-switch name="{{ $webhookField }}[{{ $index }}][active]" value="1" wire:model.change="{{ $webhookState }}.{{ $index }}.active" label="Activo" />
                        </div>
                        <button type="button" wire:click="{{ $removeWebhook }}({{ $index }})" class="rounded-lg border border-rose-300/20 bg-rose-500/20 px-3 py-2 text-white hover:bg-rose-500/30">Quitar</button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="border-t border-white/10 pt-5">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h4 class="font-semibold text-white">Condiciones {{ ucfirst($provider) }}</h4>
                <p class="text-sm text-white/50">Relaciona valores del lead con uno de los webhooks configurados.</p>
            </div>
            <button type="button" wire:click="{{ $addCondition }}" class="rounded-xl border border-indigo-300/20 bg-indigo-500/30 px-3 py-2 text-white transition hover:bg-indigo-500/40">Agregar condicion</button>
        </div>

        <div class="space-y-3">
            @foreach($conditions as $index => $condition)
                <div wire:key="{{ $provider }}-condition-{{ $condition['_key'] }}" class="grid grid-cols-1 gap-3 rounded-xl border border-white/10 bg-slate-900/40 p-4 lg:grid-cols-5">
                    <div>
                        <label class="{{ $labelClass }}">Campo Lead</label>
                        <select name="{{ $conditionField }}[{{ $index }}][lead_field]" wire:model.change="{{ $conditionState }}.{{ $index }}.lead_field" class="{{ $inputClass }}" required>
                            <option value="">Seleccione...</option>
                            @foreach($leadFields as $field)<option value="{{ $field }}">{{ $field }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Valor esperado</label>
                        <input name="{{ $conditionField }}[{{ $index }}][expected_value]" wire:model.blur="{{ $conditionState }}.{{ $index }}.expected_value" class="{{ $inputClass }}" required>
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Webhook</label>
                        <select name="{{ $conditionField }}[{{ $index }}][webhook_key]" wire:model.change="{{ $conditionState }}.{{ $index }}.webhook_key" class="{{ $inputClass }}" required>
                            <option value="">Seleccione...</option>
                            @foreach($webhooks as $option)
                                <option value="{{ $option['key'] }}">{{ $option['name'] ?: ($option['url'] ?: 'Webhook') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Activa</label>
                        <input type="hidden" name="{{ $conditionField }}[{{ $index }}][active]" value="0">
                        <x-toggle-switch name="{{ $conditionField }}[{{ $index }}][active]" value="1" wire:model.change="{{ $conditionState }}.{{ $index }}.active" label="Sí" />
                    </div>
                    <div class="flex items-end justify-end">
                        <input type="hidden" name="{{ $conditionField }}[{{ $index }}][order]" value="{{ $index }}">
                        <button type="button" wire:click="{{ $removeCondition }}({{ $index }})" class="rounded-lg border border-rose-300/20 bg-rose-500/20 px-3 py-2 text-white hover:bg-rose-500/30">Quitar</button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
