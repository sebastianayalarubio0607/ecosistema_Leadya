@php
    $name = old('name', $template->name);
    $category = old('category', $template->category);
    $type = old('type', $template->type);
    $googleStatus = old('google_status', $template->google_status);
    $primaryForGoal = old('primary_for_goal', (int) $template->primary_for_goal);
    $lookbackDays = old('click_through_lookback_window_days', $template->click_through_lookback_window_days ?? 30);
    $defaultValue = old('default_value', $template->default_value ?? 0);
    $currencyCode = old('default_currency_code', $template->default_currency_code ?? 'COP');
    $alwaysUseDefaultValue = old('always_use_default_value', (int) $template->always_use_default_value);
    $estadoLq = old('estado_lq', isset($template->id) ? (int) $template->estado_lq : 1);
@endphp

<div class="space-y-5 text-white/80">
    <div class="grid gap-4 lg:grid-cols-2">
        <div>
            <label class="block mb-1 text-white/70">Nombre *</label>
            <input name="name"
                   value="{{ $name }}"
                   class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white placeholder-white/40"
                   placeholder="API - REGISTRO FORM" />
            @error('name') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block mb-1 text-white/70">Estado LQ</label>
            <input type="hidden" name="estado_lq" value="0">
            <x-toggle-switch
                name="estado_lq"
                value="1"
                label="Plantilla activa para LQ"
                :checked="(string) $estadoLq === '1'"
            >
                Solo las plantillas activas se revisan y crean automaticamente en Google Ads.
            </x-toggle-switch>
            @error('estado_lq') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div>
            <label class="block mb-1 text-white/70">Categoria Google *</label>
            <select name="category" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white">
                @foreach($categoryOptions as $option)
                    <option value="{{ $option }}" @selected($category === $option)>{{ $option }}</option>
                @endforeach
            </select>
            @error('category') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block mb-1 text-white/70">Tipo *</label>
            <select name="type" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white">
                @foreach($typeOptions as $option)
                    <option value="{{ $option }}" @selected($type === $option)>{{ $option }}</option>
                @endforeach
            </select>
            @error('type') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block mb-1 text-white/70">Status Google *</label>
            <select name="google_status" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white">
                @foreach($statusOptions as $option)
                    <option value="{{ $option }}" @selected($googleStatus === $option)>{{ $option }}</option>
                @endforeach
            </select>
            @error('google_status') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div>
            <label class="block mb-1 text-white/70">Ventana click-through *</label>
            <input name="click_through_lookback_window_days"
                   type="number"
                   min="1"
                   max="90"
                   value="{{ $lookbackDays }}"
                   class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white placeholder-white/40" />
            @error('click_through_lookback_window_days') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block mb-1 text-white/70">Valor predeterminado *</label>
            <input name="default_value"
                   type="number"
                   min="0"
                   step="0.01"
                   value="{{ $defaultValue }}"
                   class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white placeholder-white/40" />
            @error('default_value') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block mb-1 text-white/70">Divisa *</label>
            <input name="default_currency_code"
                   value="{{ $currencyCode }}"
                   maxlength="3"
                   class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white placeholder-white/40"
                   placeholder="COP" />
            @error('default_currency_code') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div>
            <input type="hidden" name="primary_for_goal" value="0">
            <x-toggle-switch
                name="primary_for_goal"
                value="1"
                label="Primary for goal"
                :checked="(string) $primaryForGoal === '1'"
            >
                Marca la conversion como principal para objetivos de Google Ads.
            </x-toggle-switch>
            @error('primary_for_goal') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
        </div>

        <div>
            <input type="hidden" name="always_use_default_value" value="0">
            <x-toggle-switch
                name="always_use_default_value"
                value="1"
                label="Usar siempre valor predeterminado"
                :checked="(string) $alwaysUseDefaultValue === '1'"
            >
                Fuerza el valor de esta plantilla cuando Google cree la conversion.
            </x-toggle-switch>
            @error('always_use_default_value') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="rounded-xl border border-white/10 bg-white/5 p-4">
        <div class="text-sm text-white/50">Payload que se enviara a Google</div>
        <pre class="mt-3 max-h-80 overflow-auto whitespace-pre-wrap rounded-xl bg-slate-900/70 p-3 text-xs text-white/80">{{ json_encode([
    'create' => [
        'name' => $name ?: 'API - REGISTRO FORM',
        'category' => $category ?: 'SUBMIT_LEAD_FORM',
        'type' => $type ?: 'UPLOAD_CLICKS',
        'status' => $googleStatus ?: 'ENABLED',
        'primaryForGoal' => (bool) $primaryForGoal,
        'clickThroughLookbackWindowDays' => (int) $lookbackDays,
        'valueSettings' => [
            'defaultValue' => (float) $defaultValue,
            'defaultCurrencyCode' => strtoupper((string) $currencyCode),
            'alwaysUseDefaultValue' => (bool) $alwaysUseDefaultValue,
        ],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
    </div>

    <div class="flex flex-wrap gap-2 pt-2">
        <button class="rounded-xl border border-white/10 bg-indigo-500/30 px-4 py-2 text-white hover:bg-indigo-500/40">
            Guardar
        </button>
        <a href="{{ route('google-ads.conversion-templates.index') }}"
           class="rounded-xl border border-white/10 bg-white/10 px-4 py-2 text-white hover:bg-white/15">
            Cancelar
        </a>
    </div>
</div>
