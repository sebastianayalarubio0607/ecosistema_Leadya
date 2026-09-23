<div class="space-y-5" wire:poll.60s>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h2 class="text-xl font-semibold">Mi bandeja</h2><p class="text-sm text-white/70">Una incidencia por aviso. Cada confirmación es personal.</p></div>
        <label class="text-sm">Mostrar
            <select wire:model.live="filter" class="ml-2 rounded-xl border-white/20 bg-slate-900 text-white">
                <option value="pending">Pendientes</option><option value="unread">No leídas</option><option value="all">Todas</option>
            </select>
        </label>
    </div>
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
        <div class="space-y-3">
            @forelse ($items as $item)
                <button type="button" wire:click="open({{ $item->id }})" wire:key="recipient-{{ $item->id }}"
                    class="w-full text-left rounded-2xl border p-5 transition hover:bg-white/10 {{ $selectedId === $item->id ? 'border-teal-300/70 bg-white/10' : 'border-white/10 bg-slate-950/30' }}">
                    <div class="flex flex-wrap items-center gap-2 text-xs mb-3">
                        <span class="rounded-full px-2 py-1 {{ $item->latestNotification?->severity === 'critical' ? 'bg-rose-500/30 text-rose-100' : 'bg-white/10 text-white/80' }}">{{ ['info'=>'Informativa','warning'=>'Advertencia','critical'=>'Crítica'][$item->latestNotification?->severity ?? 'warning'] }}</span>
                        @if (!$item->read_at)<span class="rounded-full bg-sky-400/20 px-2 py-1 text-sky-100">Nueva</span>@endif
                        @if ($item->requires_response && !$item->acknowledged_at)<span class="rounded-full bg-amber-400/20 px-2 py-1 text-amber-100">Requiere respuesta</span>@endif
                        @if ($item->alert->status === 'resolved')<span class="rounded-full bg-emerald-400/20 px-2 py-1 text-emerald-100">Resuelta</span>@endif
                        @if ($item->acknowledged_at)<span class="rounded-full bg-white/10 px-2 py-1">Confirmada</span>@endif
                    </div>
                    <h3 class="font-semibold">{{ $item->alert->customer_name }}</h3>
                    <p class="mt-1 text-sm text-white/80 break-words">{{ $item->alert->message }}</p>
                    <p class="mt-3 text-xs text-white/60">{{ $item->last_notified_at?->format('d/m/Y H:i') }} · {{ $item->notification_count }} {{ $item->notification_count === 1 ? 'aviso' : 'avisos' }}</p>
                </button>
            @empty
                <div class="rounded-2xl border border-white/10 bg-slate-950/30 p-10 text-center"><h3 class="text-lg font-medium">No hay alertas en esta vista</h3><p class="mt-2 text-sm text-white/60">Los avisos dirigidos a ti aparecerán aquí cuando cumplan la configuración.</p></div>
            @endforelse
            {{ $items->links() }}
        </div>
        <div class="rounded-2xl border border-white/10 bg-slate-950/30 p-5 self-start space-y-4">
            @if ($selected)
                <div><p class="text-xs text-white/60">Incidencia #{{ $selected->alert_id }}</p><h3 class="mt-1 text-lg font-semibold">{{ $selected->alert->type->name }}</h3></div>
                <p class="text-white/80 break-words">{{ $selected->alert->message }}</p>
                <p class="text-xs text-white/50">{{ $selected->alert->type->subcategory->category->name }} → {{ $selected->alert->type->subcategory->name }}</p>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-white/50">Cliente</dt><dd>{{ $selected->alert->customer_name }}</dd></div>
                    <div><dt class="text-white/50">Cuenta</dt><dd class="break-all">{{ $selected->alert->entity_id }}</dd></div>
                    <div><dt class="text-white/50">{{ isset($selected->alert->metadata['metric_subscription_id']) ? 'Estado de la medición' : 'Estado actual de Meta' }}</dt><dd>{{ $selected->alert->current_state }}</dd></div>
                    <div><dt class="text-white/50">Detectada</dt><dd>{{ $selected->alert->detected_at->format('d/m/Y H:i') }}</dd></div>
                </dl>
                @if (isset($selected->alert->metadata['metric_subscription_id']))
                    <div class="space-y-2 text-sm">@foreach ($selected->alert->metadata['rows'] as $measurement)<div class="rounded-xl border border-white/10 p-3"><p>{{ strtoupper($measurement['platform']) }} · {{ $measurement['name'] }} · {{ $measurement['count'] }} / mínimo {{ $measurement['threshold'] }}</p><p class="mt-1 text-xs text-white/60">{{ $measurement['window_start'] }} → {{ $measurement['window_end'] }} (fin exclusivo)</p></div>@endforeach</div>
                @endif
                @if ($selected->alert->resolved_at)<p class="rounded-xl bg-emerald-400/10 p-3 text-sm text-emerald-100">{{ isset($selected->alert->metadata['metric_subscription_id']) ? 'Se verificó la recuperación el' : 'Meta reportó la recuperación el' }} {{ $selected->alert->resolved_at->format('d/m/Y H:i') }}. @if($selected->requires_response && !$selected->acknowledged_at)Tu confirmación sigue pendiente.@endif</p>@endif
                @if (!$selected->acknowledged_at)
                    <form wire:submit="acknowledge" class="space-y-3">
                        <label for="alert-comment" class="block text-sm">{{ $selected->requires_response ? 'Comentario obligatorio para confirmar' : 'Confirmar con un comentario' }}</label>
                        <textarea id="alert-comment" wire:model="comment" rows="4" maxlength="4000" required class="w-full rounded-xl border-white/20 bg-slate-900/80 text-white placeholder-white/40" placeholder="Indica que te enteraste o describe la gestión realizada."></textarea>
                        @error('comment')<p class="text-sm text-rose-200" role="alert">{{ $message }}</p>@enderror
                        <button wire:loading.attr="disabled" class="rounded-xl bg-teal-500/30 hover:bg-teal-500/40 border border-teal-300/30 px-4 py-2 disabled:opacity-50">Confirmar y quitar de pendientes</button>
                    </form>
                @else
                    <p class="text-sm text-emerald-200">Confirmaste el {{ $selected->acknowledged_at->format('d/m/Y H:i') }}.</p>
                @endif
                @if (!$selected->requires_response && !$selected->dismissed_at)<button type="button" wire:click="dismiss({{ $selected->id }})" class="text-sm text-white/70 underline">Archivar aviso</button>@endif
                @foreach ($selected->comments as $entry)<div class="border-t border-white/10 pt-3 text-sm"><p class="text-white/50">{{ $entry->user?->name }} · {{ $entry->created_at->format('d/m/Y H:i') }}</p><p class="mt-1 whitespace-pre-wrap break-words">{{ $entry->body }}</p></div>@endforeach
            @else
                <p class="text-white/60 text-sm">Selecciona una alerta para leerla y registrar tu confirmación.</p>
            @endif
        </div>
    </div>
</div>
