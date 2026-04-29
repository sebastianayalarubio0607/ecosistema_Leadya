@php
    $isEdit = $credential->exists;
    $expiresAt = old('access_token_expires_at', optional($credential->access_token_expires_at)->format('Y-m-d\TH:i'));
    $secretLabels = [
        'mcc_developer_token' => 'Developer Token MCC',
        'client_id' => 'Client ID',
        'client_secret' => 'Client Secret',
        'refresh_token' => 'Refresh Token',
        'access_token' => 'Access Token',
        'customer_id' => 'Customer ID de Google Ads',
        'mcc_id' => 'MCC ID',
    ];
@endphp

<div class="space-y-4 text-white/80">
    <div>
        <label class="block mb-1 text-white/70">Activo *</label>
        <select name="is_active" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
            <option value="1" @selected((string) old('is_active', (int) $credential->is_active) === '1')>Sí</option>
            <option value="0" @selected((string) old('is_active', (int) $credential->is_active) === '0')>No</option>
        </select>
        @error('is_active') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block mb-1 text-white/70">Expira el Access Token</label>
        <input type="datetime-local"
               name="access_token_expires_at"
               value="{{ $expiresAt }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" />
        @error('access_token_expires_at') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
    </div>

    @foreach($secretLabels as $field => $label)
        <div class="rounded-2xl border border-white/10 bg-white/5 p-4 space-y-3">
            @if($isEdit)
                @include('google_ads.partials.reveal-secret', [
                    'credential' => $credential,
                    'field' => $field,
                    'label' => $label.' actual',
                    'rows' => in_array($field, ['refresh_token', 'access_token', 'client_secret'], true) ? 3 : 2,
                ])
            @endif

            <div>
                <label class="block mb-1 text-white/70">
                    {{ $isEdit ? 'Nuevo '.$label.' (opcional)' : $label.' *' }}
                </label>

                @if(in_array($field, ['refresh_token', 'access_token', 'client_secret'], true))
                    <textarea name="{{ $field }}" rows="3"
                              class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
                              placeholder="{{ $isEdit ? 'Déjalo vacío para conservar el valor actual' : 'Ingresa el valor' }}">{{ old($field) }}</textarea>
                @else
                    <input name="{{ $field }}"
                           value="{{ old($field) }}"
                           class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
                           placeholder="{{ $isEdit ? 'Déjalo vacío para conservar el valor actual' : 'Ingresa el valor' }}" />
                @endif

                @error($field) <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
            </div>
        </div>
    @endforeach
</div>
