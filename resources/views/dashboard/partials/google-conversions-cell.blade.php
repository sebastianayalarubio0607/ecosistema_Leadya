@php
    $events = collect($row['conversion_events'] ?? [])
        ->filter(fn ($event) => is_array($event) && (float) ($event['quantity_value'] ?? 0) > 0)
        ->values();
    $total = $row['conversions'] ?? '0,00';
@endphp

@if($events->isEmpty())
    <span>{{ $total }}</span>
@else
    <div class="inline-block text-left" x-data="{ open: false }" onclick="event.stopPropagation()" @keydown.escape.window="open = false">
        <button
            type="button"
            class="inline-flex min-h-8 items-center gap-2 rounded-lg border border-sky-300/20 bg-sky-300/10 px-2 py-1 text-xs font-semibold text-sky-100 hover:bg-sky-300/15 focus:outline-none focus:ring-2 focus:ring-sky-200/40"
            title="Ver conversiones por evento"
            @click.stop="open = ! open"
        >
            <span>{{ $total }}</span>
            <span class="text-[10px] font-semibold text-sky-100/70">Eventos</span>
        </button>

        <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4" @click.self="open = false">
            <div class="w-full max-w-md rounded-xl border border-white/10 bg-slate-950 p-4 text-left shadow-xl shadow-black/30">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <div class="text-xs font-semibold text-white">Conversiones Por Evento</div>
                        <div class="mt-1 text-xs text-white/50">Total: {{ $total }}</div>
                    </div>
                    <button
                        type="button"
                        class="rounded-md px-2 py-1 text-xs text-white/60 hover:bg-white/10 hover:text-white"
                        title="Cerrar"
                        @click.stop="open = false"
                    >
                        Cerrar
                    </button>
                </div>
                <div class="max-h-72 overflow-y-auto">
                    @foreach($events as $event)
                        <div class="flex items-start justify-between gap-3 border-t border-white/10 py-2 first:border-t-0 first:pt-0">
                            <span class="min-w-0 whitespace-normal break-words text-xs text-white/75">{{ $event['name'] }}</span>
                            <span class="shrink-0 text-xs font-semibold text-white">{{ $event['quantity'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endif
