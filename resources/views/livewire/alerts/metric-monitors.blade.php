<div class="space-y-6">
    <div class="flex flex-wrap justify-between gap-3">
        <div><h2 class="text-xl font-semibold">Alertas de impresiones y leads</h2><p class="mt-1 text-sm text-white/60">Una configuración compartida, con periodos, destinatarios y horarios propios para cada cliente.</p></div>
        <button wire:click="create" class="rounded-xl border border-teal-300/30 bg-teal-500/30 px-4 py-2 hover:bg-teal-500/40">Crear alerta</button>
    </div>
    @if (session('metric-saved'))<p role="status" class="rounded-xl bg-emerald-400/10 p-4 text-emerald-100">{{ session('metric-saved') }}</p>@endif
    @if ($editing)
        <form wire:submit="save" class="rounded-2xl border border-white/10 bg-slate-950/30 p-5 space-y-5">
            <h3 class="text-lg font-semibold">{{ $subscriptionId ? 'Configuración del cliente' : ($monitorId ? 'Asociar clientes a la alerta' : 'Crear alerta') }}</h3>
            @if ($errors->any())<ul role="alert" class="rounded-xl bg-rose-500/10 p-4 text-sm text-rose-200">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-white/10 p-3 text-sm"><span class="text-white/50">Categoría</span><p>{{ $form['kind'] === 'impressions' ? 'Publicidad' : 'Leads Quality' }}</p></div>
                <div class="rounded-xl border border-white/10 p-3 text-sm"><span class="text-white/50">Subcategoría</span><p>{{ $form['kind'] === 'impressions' ? 'Impresiones' : 'Creación de leads' }}</p></div>
                <label class="text-sm">Nombre<input wire:model="form.name" @disabled($monitorId) class="mt-1 w-full rounded-xl border-white/20 bg-slate-900" maxlength="150" required></label>
                <label class="text-sm">Tipo<select wire:model.live="form.kind" @disabled($monitorId) class="mt-1 w-full rounded-xl border-white/20 bg-slate-900"><option value="impressions">Impresiones en plataformas</option><option value="leads">Leads creados en Leads Quality</option></select></label>
                <label class="text-sm">Clientes<select multiple wire:model="form.customer_ids" @disabled($subscriptionId) class="mt-1 h-36 w-full rounded-xl border-white/20 bg-slate-900">@foreach ($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}{{ $customer->status ? '' : ' (inactivo)' }}</option>@endforeach</select></label>
                <label class="text-sm">Destinatarios del aviso interno<select multiple wire:model="form.user_ids" class="mt-1 h-36 w-full rounded-xl border-white/20 bg-slate-900">@foreach ($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></label>
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="form.enabled" class="rounded border-white/20 bg-slate-900 text-teal-500">Activar para los clientes seleccionados</label>
            <p class="text-xs text-white/60">Desactiva esta opción para el cliente que no deba consultar ni recibir avisos. Los clientes inactivos se omiten automáticamente.</p>
            @if ($form['kind'] === 'impressions')
                <fieldset class="space-y-3"><legend class="font-medium">Consulta directa a las plataformas</legend>
                    <div class="flex gap-5">@foreach (['meta'=>'Meta Ads', 'google'=>'Google Ads'] as $value=>$label)<label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="form.settings.platforms" value="{{ $value }}" class="rounded bg-slate-900 text-teal-500">{{ $label }}</label>@endforeach</div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="text-sm">Evaluar cada<select wire:model.live="form.settings.level" class="mt-1 w-full rounded-xl border-white/20 bg-slate-900"><option value="campaign">Campaña</option><option value="group">Grupo / conjunto de anuncios</option><option value="ad">Anuncio</option></select></label>
                        <label class="text-sm">Presentación del aviso<select wire:model="form.settings.grouping" class="mt-1 w-full rounded-xl border-white/20 bg-slate-900"><option value="separate">Separado por entidad y plataforma</option><option value="together">Un aviso con todas las entidades que incumplan</option></select></label>
                        <label class="text-sm">IDs de entidades Meta (opcional)<input wire:model="metaIds" class="mt-1 w-full rounded-xl border-white/20 bg-slate-900" placeholder="123, 456"></label>
                        <label class="text-sm">IDs de entidades Google (opcional)<input wire:model="googleIds" class="mt-1 w-full rounded-xl border-white/20 bg-slate-900" placeholder="123, 456"></label>
                    </div>
                    <p class="text-xs text-white/60">Sin IDs se consultan todas las entidades activas del nivel elegido en las cuentas asociadas al cliente. Usa IDs externos de la plataforma. Los avisos conjuntos mantienen el resultado de cada entidad; las impresiones de una plataforma no compensan las de otra.</p>
                </fieldset>
            @endif
            <fieldset class="grid gap-4 md:grid-cols-3"><legend class="mb-3 font-medium">Condición de la alerta</legend>
                <label class="text-sm">Cómo medir el periodo<select wire:model.live="form.settings.window_mode" class="mt-1 w-full rounded-xl border-white/20 bg-slate-900"><option value="hours">Últimas horas completas</option><option value="days">Últimos días calendario completos</option></select></label>
                <label class="text-sm">Avisar por debajo de<input type="number" wire:model="form.settings.minimum" min="1" max="1000000000" class="mt-1 w-full rounded-xl border-white/20 bg-slate-900" required></label>
                <label class="text-sm">Periodo en horas<input type="number" wire:model="form.settings.window_hours" min="1" max="720" class="mt-1 w-full rounded-xl border-white/20 bg-slate-900" required></label>
                @if ($form['kind'] === 'impressions')<label class="text-sm">Margen de retraso del reporte (horas)<input type="number" wire:model="form.settings.lag_hours" min="0" max="72" class="mt-1 w-full rounded-xl border-white/20 bg-slate-900" required></label>@endif
            </fieldset>
            @if ($form['settings']['window_mode'] === 'days')<p class="rounded-xl border border-sky-300/20 bg-sky-500/10 p-3 text-sm text-sky-100">Se miden días calendario completos: 24 = 1 día, 48 = 2 días, 72 = 3 días. Usa múltiplos de 24. El cierre del último día debe superar el margen de retraso. Google Ads requiere este modo para anuncios; campañas y grupos también admiten horas.</p>@endif
            <p class="text-xs text-white/60">Mínimo 1 significa avisar cuando hay cero. Tres días equivalen a 72 horas continuas. Se miden horas completas: por ejemplo, a las 10:30, un periodo de 24 h con margen 3 h termina a las 07:00. En publicidad se usa la zona horaria de cada cuenta y los datos disponibles al consultar.</p>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="form.settings.warmup" class="rounded bg-slate-900 text-teal-500">Esperar un periodo completo desde la activación o desde que se descubre una entidad nueva</label>
            <label class="block text-sm">Zona horaria de los horarios<input wire:model="form.settings.timezone" list="metric-timezones" class="mt-1 w-full md:w-1/2 rounded-xl border-white/20 bg-slate-900" required></label>
            <datalist id="metric-timezones"><option value="America/Bogota"><option value="America/Mexico_City"><option value="America/Lima"><option value="Europe/Madrid"><option value="UTC"></datalist>
            <div class="grid gap-5 xl:grid-cols-2">
                @foreach (['query'=>'Horario de consulta', 'notify'=>'Horario de notificación'] as $phase=>$label)
                    <fieldset class="rounded-xl border border-white/10 p-4 space-y-3"><legend class="px-2 font-medium">{{ $label }}</legend>
                        <div class="flex flex-wrap gap-3">@foreach ([1=>'Lu',2=>'Ma',3=>'Mi',4=>'Ju',5=>'Vi',6=>'Sá',7=>'Do'] as $day=>$name)<label class="flex items-center gap-1 text-sm"><input type="checkbox" wire:model="form.settings.{{ $phase }}_days" value="{{ $day }}" class="rounded bg-slate-900 text-teal-500">{{ $name }}</label>@endforeach</div>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="text-sm">Desde<input type="time" wire:model="form.settings.{{ $phase }}_start" class="mt-1 w-full rounded-xl border-white/20 bg-slate-900" required></label>
                            <label class="text-sm">Hasta<input type="time" wire:model="form.settings.{{ $phase }}_end" class="mt-1 w-full rounded-xl border-white/20 bg-slate-900" required></label>
                        </div>
                        <label class="block text-sm">{{ $phase === 'query' ? 'Consultar cada (minutos)' : 'Separación mínima entre avisos (minutos)' }}<input type="number" min="{{ $phase === 'query' ? 5 : 1 }}" max="10080" wire:model="form.settings.{{ $phase }}_interval" class="mt-1 w-full rounded-xl border-white/20 bg-slate-900" required></label>
                    </fieldset>
                @endforeach
            </div>
            <p class="text-xs text-white/60">00:00–00:00 permite todo el día. Los horarios pueden cruzar medianoche y pertenecen al día de inicio. Los días seleccionados controlan consultas y avisos; el periodo medido incluye fines de semana. Una consulta fuera del horario de aviso queda pendiente hasta la próxima franja, siempre que siga vigente.</p>
            <div class="grid gap-4 md:grid-cols-3">
                <label class="text-sm">Máximo de avisos por incidencia<input type="number" wire:model="form.settings.max_notifications" min="1" max="100" class="mt-1 w-full rounded-xl border-white/20 bg-slate-900" required></label>
                <label class="text-sm">Vigencia del resultado para avisar (horas)<input type="number" wire:model="form.settings.fresh_hours" min="1" max="168" class="mt-1 w-full rounded-xl border-white/20 bg-slate-900" required></label>
                <label class="text-sm">Prioridad<select wire:model="form.settings.severity" class="mt-1 w-full rounded-xl border-white/20 bg-slate-900"><option value="info">Informativa</option><option value="warning">Advertencia</option><option value="critical">Crítica</option></select></label>
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="form.settings.requires_response" class="rounded bg-slate-900 text-teal-500">Exigir confirmación con comentario</label>
            @if ($subscriptionId)<p class="text-xs text-white/60">Guardar inicia una versión nueva de esta configuración y conserva las incidencias y confirmaciones anteriores en el historial.</p>@endif
            <div class="flex gap-3"><button wire:loading.attr="disabled" class="rounded-xl border border-teal-300/30 bg-teal-500/30 px-5 py-2 disabled:opacity-50">Guardar</button><button type="button" wire:click="$set('editing', false)" class="rounded-xl border border-white/20 px-5 py-2">Cancelar</button></div>
            @if ($runs->isNotEmpty())<div class="border-t border-white/10 pt-3 text-xs text-white/60"><p class="font-medium">Actividad reciente</p>@foreach ($runs as $run)<p>{{ $run->checked_at->format('d/m/Y H:i') }} · {{ ['configured'=>'Configuración guardada','complete'=>'Consulta completa','unavailable'=>'Datos no disponibles','no_entities'=>'Sin entidades activas'][$run->status] ?? $run->status }} · versión {{ $run->version }}</p>@endforeach</div>@endif
        </form>
    @endif
    @forelse ($monitors as $monitor)
        <section wire:key="metric-{{ $monitor->id }}" class="rounded-2xl border border-white/10 bg-slate-950/30 p-5 space-y-4">
            <div class="flex flex-wrap justify-between gap-3"><div><h3 class="font-semibold">{{ $monitor->name }}</h3><p class="text-xs text-white/60">{{ $monitor->kind === 'leads' ? 'Leads Quality' : 'Impresiones · consulta directa a API' }}</p></div><button wire:click="create({{ $monitor->id }})" class="text-sm text-teal-200 underline">Asociar más clientes</button></div>
            @foreach ($monitor->subscriptions as $s)
                <div wire:key="metric-sub-{{ $s->id }}" class="border-t border-white/10 pt-3 flex flex-wrap justify-between gap-3">
                    <div class="space-y-1"><p class="text-sm font-medium">{{ $s->customer?->name ?? 'Cliente eliminado' }} · {{ $s->enabled && $s->customer?->status ? 'Activa' : 'Desactivada' }}</p>
                        <p class="text-xs text-white/60">Menos de {{ $s->settings['minimum'] }} en {{ ($s->settings['window_mode'] ?? 'hours') === 'days' ? ($s->settings['window_hours'] / 24).' días completos' : $s->settings['window_hours'].' h' }} · consulta cada {{ $s->settings['query_interval'] }} min · {{ $s->settings['timezone'] }}</p>
                        <p class="text-xs text-white/60">Consulta {{ $s->settings['query_start'] }}–{{ $s->settings['query_end'] }} · aviso {{ $s->settings['notify_start'] }}–{{ $s->settings['notify_end'] }} · {{ $s->users->pluck('name')->join(', ') }}</p>
                        <p class="text-xs text-white/60">{{ ['pending'=>'Pendiente de consulta','querying'=>'Consultando','complete'=>'Consulta completa','unavailable'=>'Datos no disponibles','no_entities'=>'Sin entidades activas'][$s->last_status] ?? $s->last_status }}{{ $s->last_checked_at ? ' · '.$s->last_checked_at->format('d/m/Y H:i') : '' }}</p>
                        @if ($s->last_error)<p class="text-xs text-amber-200 max-w-2xl">{{ $s->last_error }}</p>@endif
                    </div>
                    @if ($s->customer_id)<button wire:click="edit({{ $s->id }})" class="self-start rounded-xl border border-white/20 px-3 py-2 text-sm hover:bg-white/10">Personalizar cliente</button>@endif
                </div>
            @endforeach
        </section>
    @empty
        <div class="rounded-2xl border border-white/10 bg-slate-950/30 p-8 text-center text-white/60">Crea una alerta y asocia los clientes que quieras supervisar.</div>
    @endforelse
    {{ $monitors->links() }}
</div>
