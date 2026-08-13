<div wire:init="load">
    @if(in_array($mode, ['summary', 'gerencial-summary'], true))
        @php
            $summaryLabel = $mode === 'gerencial-summary' ? 'Costo (Spend) en el periodo seleccionado' : 'Costo Total';
        @endphp
        @if($waiting)
            <div
                class="rounded-2xl border border-sky-300/20 bg-zinc-950/25 p-4 text-sm text-sky-100 backdrop-blur"
                x-data="{ remaining: @js($waitSeconds), ready: false }"
                x-init="const autoRetry = @js($mode !== 'gerencial-summary'); const timer = setInterval(() => { remaining = Math.max(0, remaining - 1); if (remaining === 0) { clearInterval(timer); if (autoRetry) { $wire.retry(); } else { ready = true; } } }, 1000)"
            >
                <div class="text-sm text-white/60">{{ $summaryLabel }}</div>
                <div class="mt-2 text-3xl font-bold text-white">En Espera</div>
                <div class="mt-3 text-xs font-semibold text-white/50">Límite De {{ $waitingPlatform }}</div>
                <div class="mt-1 text-base font-semibold text-white">Nueva consulta en <span x-text="remaining"></span>s</div>
                <button
                    type="button"
                    wire:click="retry"
                    x-show="ready"
                    x-cloak
                    class="mt-3 rounded-xl border border-white/10 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/15"
                >
                    Reintentar
                </button>
            </div>
        @else
            @include('dashboard.general-leads.partials.summary-card', [
                'label' => $summaryLabel,
                'value' => $error ? 'Sin Dato' : $costs['total'],
                'secondaryLabel' => 'Meta / Google',
                'secondaryValue' => $error ? 'Error De Consulta' : $costs['meta'].' / '.$costs['google'],
            ])
        @endif
    @else
        @if($waiting)
            <div
                class="rounded-2xl border border-sky-300/20 bg-sky-300/10 p-4 text-sm text-sky-100"
                x-data="{ remaining: @js($waitSeconds) }"
                x-init="const timer = setInterval(() => { remaining = Math.max(0, remaining - 1); if (remaining === 0) { clearInterval(timer); $wire.retry(); } }, 1000)"
            >
                Esperando para consultar {{ $waitingPlatform }}. Nueva consulta en
                <span class="font-bold" x-text="remaining"></span>
                segundos.
            </div>
        @elseif($error)
            <div class="rounded-2xl border border-amber-300/20 bg-amber-300/10 p-4 text-sm text-amber-100">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <span>{{ $error }}</span>
                    <button type="button" wire:click="retry" class="inline-flex min-h-10 items-center rounded-xl border border-white/10 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/15">Reintentar</button>
                </div>
            </div>
        @else
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                @include('dashboard.general-leads.partials.summary-card', ['label' => 'Costo Meta', 'value' => $costs['meta']])
                @include('dashboard.general-leads.partials.summary-card', ['label' => 'Costo Google', 'value' => $costs['google']])
                @include('dashboard.general-leads.partials.summary-card', ['label' => 'Costo Total', 'value' => $costs['total']])
            </div>
        @endif
    @endif
</div>
