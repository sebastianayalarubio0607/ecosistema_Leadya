@php
    $selectedTools = old('allowed_tools', $connector->allowed_tools ?: \App\Models\AiConnector::defaultTools());
    $origins = old('allowed_origins', implode(PHP_EOL, $connector->allowed_origins ?: []));
@endphp

<div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
    <section class="rounded-2xl border border-white/10 bg-zinc-950/25 p-4 backdrop-blur xl:col-span-7 space-y-4">
        <div>
            <label class="mb-1 block text-sm text-white/70">Nombre</label>
            <input name="name" value="{{ old('name', $connector->name) }}" required maxlength="255"
                   class="w-full rounded-xl border-white/10 bg-slate-900/60 text-white">
        </div>

        <div>
            <label class="mb-1 block text-sm text-white/70">Restriccion por cliente</label>
            <select name="customer_id" class="w-full rounded-xl border-white/10 bg-slate-900/60 text-white">
                <option value="">Todos los clientes</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" @selected((string) old('customer_id', $connector->customer_id) === (string) $customer->id)>
                        {{ $customer->name }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-white/50">Si eliges un cliente, el conector no podra consultar otros clientes aunque la IA envie otro customer_id.</p>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <label class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-3 py-3 text-white/80">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $connector->is_active ?? true)) class="rounded border-white/10 bg-slate-900">
                <span>Conector activo</span>
            </label>

            <label class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-3 py-3 text-white/80">
                <input type="hidden" name="allow_ad_metrics" value="0">
                <input type="checkbox" name="allow_ad_metrics" value="1" @checked((bool) old('allow_ad_metrics', $connector->allow_ad_metrics)) class="rounded border-white/10 bg-slate-900">
                <span>Permitir costos y pauta</span>
            </label>
        </div>

        <div>
            <label class="mb-2 block text-sm text-white/70">Herramientas permitidas</label>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                @foreach($tools as $tool => $label)
                    <label class="flex items-start gap-3 rounded-xl border border-white/10 bg-white/5 px-3 py-3 text-sm text-white/80">
                        <input type="checkbox" name="allowed_tools[]" value="{{ $tool }}" @checked(in_array($tool, $selectedTools, true)) class="mt-1 rounded border-white/10 bg-slate-900">
                        <span>
                            <span class="block font-semibold text-white">{{ $label }}</span>
                            <span class="block text-xs text-white/50">{{ $tool }}</span>
                            @if(in_array($tool, $adTools, true))
                                <span class="mt-1 block text-xs text-amber-100/70">Requiere permitir costos y pauta.</span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-white/10 bg-zinc-950/25 p-4 backdrop-blur xl:col-span-5 space-y-4">
        <h3 class="text-lg font-semibold text-white">Seguridad y capacidad</h3>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm text-white/70">Consultas por minuto</label>
                <input type="number" name="max_requests_per_minute" min="1" max="1000" value="{{ old('max_requests_per_minute', $connector->max_requests_per_minute ?? 20) }}"
                       class="w-full rounded-xl border-white/10 bg-slate-900/60 text-white">
            </div>
            <div>
                <label class="mb-1 block text-sm text-white/70">Consultas por dia</label>
                <input type="number" name="max_requests_per_day" min="1" max="100000" value="{{ old('max_requests_per_day', $connector->max_requests_per_day ?? 1000) }}"
                       class="w-full rounded-xl border-white/10 bg-slate-900/60 text-white">
            </div>
            <div>
                <label class="mb-1 block text-sm text-white/70">Intervalo minimo</label>
                <input type="number" name="min_request_interval_seconds" min="0" max="300" value="{{ old('min_request_interval_seconds', $connector->min_request_interval_seconds ?? 1) }}"
                       class="w-full rounded-xl border-white/10 bg-slate-900/60 text-white">
            </div>
            <div>
                <label class="mb-1 block text-sm text-white/70">Rango maximo dias</label>
                <input type="number" name="max_date_range_days" min="1" max="366" value="{{ old('max_date_range_days', $connector->max_date_range_days ?? 31) }}"
                       class="w-full rounded-xl border-white/10 bg-slate-900/60 text-white">
            </div>
            <div>
                <label class="mb-1 block text-sm text-white/70">Cache segundos</label>
                <input type="number" name="cache_ttl_seconds" min="0" max="86400" value="{{ old('cache_ttl_seconds', $connector->cache_ttl_seconds ?? 300) }}"
                       class="w-full rounded-xl border-white/10 bg-slate-900/60 text-white">
            </div>
            <div>
                <label class="mb-1 block text-sm text-white/70">Token minutos</label>
                <input type="number" name="access_token_ttl_minutes" min="5" max="1440" value="{{ old('access_token_ttl_minutes', $connector->access_token_ttl_minutes ?? 60) }}"
                       class="w-full rounded-xl border-white/10 bg-slate-900/60 text-white">
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm text-white/70">Origins HTTP permitidos</label>
            <textarea name="allowed_origins" rows="4"
                      class="w-full rounded-xl border-white/10 bg-slate-900/60 text-white placeholder-white/40"
                      placeholder="https://chat.openai.com&#10;https://claude.ai">{{ $origins }}</textarea>
            <p class="mt-1 text-xs text-white/50">Uno por linea. Las llamadas servidor a servidor normalmente no envian Origin.</p>
        </div>

        <div>
            <label class="mb-1 block text-sm text-white/70">Notas internas</label>
            <textarea name="notes" rows="4"
                      class="w-full rounded-xl border-white/10 bg-slate-900/60 text-white">{{ old('notes', $connector->notes) }}</textarea>
        </div>
    </section>
</div>

<div class="mt-4 flex items-center gap-2">
    <button class="rounded-xl border border-white/10 bg-indigo-500/30 px-4 py-2 font-semibold text-white hover:bg-indigo-500/40">
        Guardar
    </button>
    <a href="{{ route('ai-connectors.index') }}"
       class="rounded-xl border border-white/10 bg-white/10 px-4 py-2 font-semibold text-white/80 hover:bg-white/15">
        Cancelar
    </a>
</div>
