<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block mb-1 text-white/70">Nombre interno</label>
        <input name="name"
               value="{{ old('name', $accessToken->name ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
               placeholder="Ej. WhatsApp cliente A / Default WhatsApp">
        <p class="mt-1 text-xs leading-relaxed text-white/50">Usa un nombre facil de reconocer internamente. Sirve para identificar de que cliente, app o uso viene el token sin tener que revisar el valor secreto.</p>
        @error('name') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block mb-1 text-white/70">Proposito *</label>
        @php($selectedPurpose = old('purpose', $accessToken->purpose ?? \App\Models\MetaAccessToken::PURPOSE_GENERAL))
        <select name="purpose"
                class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
            @foreach($purposes as $purpose)
                <option value="{{ $purpose }}" @selected($selectedPurpose === $purpose)>
                    {{ $purpose }}
                </option>
            @endforeach
        </select>
        <p class="mt-1 text-xs leading-relaxed text-white/50">Selecciona general para flujos actuales de Meta como paginas y leads. Selecciona whatsapp cuando el token se usara para consultar o suscribir cuentas de WhatsApp Business.</p>
        @error('purpose') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
    </div>

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
        <p class="mt-1 text-xs leading-relaxed text-white/50">user_access_token se usa para credenciales de usuario que el sistema puede convertir o refrescar. system_access_token se usa para usuarios del sistema de Meta; para WhatsApp este es el tipo requerido.</p>
        @error('token_type') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block mb-1 text-white/70">Customer asociado</label>
        @php($selectedCustomerId = old('customer_id', $accessToken->customer_id ?? ''))
        <select name="customer_id"
                class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
            <option value="">Sin customer</option>
            @foreach(($customers ?? collect()) as $customer)
                <option value="{{ $customer->id }}" @selected((string) $selectedCustomerId === (string) $customer->id)>
                    {{ $customer->name }} (ID: {{ $customer->id }})
                </option>
            @endforeach
        </select>
        <p class="mt-1 text-xs leading-relaxed text-white/50">Dejalo en Sin customer si sera global. Para WhatsApp, cuando la WABA pertenezca a este customer, este token se usara antes que el default.</p>
        @error('customer_id') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block mb-1 text-white/70">Activo</label>
        @php($isActive = (string) old('is_active', isset($accessToken) ? (int) $accessToken->is_active : 1))
        <select name="is_active"
                class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
            <option value="1" @selected($isActive === '1')>Si</option>
            <option value="0" @selected($isActive === '0')>No</option>
        </select>
        <p class="mt-1 text-xs leading-relaxed text-white/50">Marca Si para permitir que el sistema use esta credencial en consultas y jobs. Marca No para conservarla registrada pero excluirla de nuevos procesos automaticos.</p>
        @error('is_active') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block mb-1 text-white/70">Default WhatsApp</label>
        @php($isDefault = (string) old('is_default', isset($accessToken) ? (int) $accessToken->is_default : 0))
        <select name="is_default"
                class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
            <option value="0" @selected($isDefault === '0')>No</option>
            <option value="1" @selected($isDefault === '1')>Si</option>
        </select>
        <p class="mt-1 text-xs leading-relaxed text-white/50">Usa Si solo para el token WhatsApp de respaldo. Debe tener purpose=whatsapp, tipo system_access_token, estar activo y no tener customer asociado.</p>
        @error('is_default') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block mb-1 text-white/70">Meta App ID</label>
        <input name="meta_app_id"
               value="{{ old('meta_app_id', $accessToken->meta_app_id ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
               placeholder="Requerido para tokens WhatsApp">
        <p class="mt-1 text-xs leading-relaxed text-white/50">Pega el ID numerico de la aplicacion de Meta dueña de esta credencial. En WhatsApp define contra que app se consulta la suscripcion de la WABA.</p>
        @error('meta_app_id') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block mb-1 text-white/70">Meta App Secret</label>
        <input name="meta_app_secret"
               value="{{ old('meta_app_secret', $accessToken->meta_app_secret ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
               placeholder="Opcional, sobrescribe el .env para este token">
        <p class="mt-1 text-xs leading-relaxed text-white/50">Opcional. Pegalo solo si esta credencial necesita usar un App Secret distinto al configurado globalmente en el .env.</p>
        @error('meta_app_secret') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block mb-1 text-white/70">Business ID</label>
        <input name="meta_business_id"
               value="{{ old('meta_business_id', $accessToken->meta_business_id ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
               placeholder="Opcional">
        <p class="mt-1 text-xs leading-relaxed text-white/50">Opcional. Registra el Business Manager ID al que pertenece el token para auditoria y para saber de que negocio salen las WABAs o activos.</p>
        @error('meta_business_id') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block mb-1 text-white/70">System User ID</label>
        <input name="meta_system_user_id"
               value="{{ old('meta_system_user_id', $accessToken->meta_system_user_id ?? '') }}"
               class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
               placeholder="Opcional">
        <p class="mt-1 text-xs leading-relaxed text-white/50">Opcional. Guarda el ID del usuario del sistema de Meta que genero el token; ayuda a rastrear permisos, rotaciones y credenciales compartidas entre clientes.</p>
        @error('meta_system_user_id') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="block mb-1 text-white/70">Access Token {{ $accessToken->exists ? '(opcional para reemplazar)' : '*' }}</label>
        <textarea name="short_lived_token" rows="5"
                  class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
                  placeholder="Pega aqui el token">{{ old('short_lived_token') }}</textarea>
        @if($accessToken->exists)
            <p class="mt-1 text-xs leading-relaxed text-white/50">Pega el token completo de Meta solo si quieres reemplazar la credencial actual. Si lo dejas vacio se conserva el token guardado.</p>
        @else
            <p class="mt-1 text-xs leading-relaxed text-white/50">Pega el token completo generado en Meta. En general puede ser un token de usuario para intercambio; en WhatsApp debe ser el system access token que tendra permisos sobre la WABA.</p>
        @endif
        @error('short_lived_token') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
    </div>
</div>
