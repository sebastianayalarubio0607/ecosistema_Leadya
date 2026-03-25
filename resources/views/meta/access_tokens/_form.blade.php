<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block mb-1 text-white/70">Tipo *</label>
        @php($selectedType = old('token_type', $accessToken->token_type ?? \App\Models\MetaAccessToken::TYPE_USER_ACCESS_TOKEN))
        <select name="token_type"
                class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
            @foreach($tokenTypes as $tokenType)
                <option value="{{ $tokenType }}" @selected($selectedType === $tokenType)>
                    {{ $tokenType }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block mb-1 text-white/70">Activo</label>
        @php($isActive = (string) old('is_active', isset($accessToken) ? (int) $accessToken->is_active : 1))
        <select name="is_active"
                class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
            <option value="1" @selected($isActive === '1')>Sí</option>
            <option value="0" @selected($isActive === '0')>No</option>
        </select>
    </div>

    <div>
        <label class="block mb-1 text-white/70">Meta App ID</label>
        <input name="meta_app_id"
               value="{{ old('meta_app_id', $accessToken->meta_app_id ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
               placeholder="Opcional, sobrescribe el .env para este token">
    </div>

    <div>
        <label class="block mb-1 text-white/70">Meta App Secret</label>
        <input name="meta_app_secret"
               value="{{ old('meta_app_secret', $accessToken->meta_app_secret ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
               placeholder="Opcional, sobrescribe el .env para este token">
    </div>

    <div class="md:col-span-2">
        <label class="block mb-1 text-white/70">Access Token {{ $accessToken->exists ? '(opcional para reemplazar)' : '*' }}</label>
        <textarea name="short_lived_token" rows="5"
                  class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
                  placeholder="Pega aquí el token">{{ old('short_lived_token') }}</textarea>
        @if($accessToken->exists)
            <p class="mt-1 text-xs text-white/50">Si lo dejas vacío se conserva el token actual.</p>
        @endif
    </div>
</div>
