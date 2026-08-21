<div wire:init="load">
    @if (! $loaded)
        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4">
            <div class="text-sm text-white/60">Resumen por</div>
            <h3 class="text-white font-semibold">{{ $displaySection['title'] }}</h3>
            <div class="mt-3 rounded-xl border border-white/10 bg-white/5 px-4 py-8 text-center text-sm text-white/60">
                Consultando datos directamente en la plataforma...
            </div>
        </div>
    @elseif($waiting)
        <div class="rounded-2xl border border-sky-300/20 bg-zinc-950/25 backdrop-blur p-4">
            <div class="text-sm text-sky-100/70">Límite de consultas</div>
            <h3 class="text-white font-semibold">{{ $displaySection['title'] }}</h3>
            <div
                class="mt-3 rounded-xl border border-sky-300/20 bg-sky-300/10 px-4 py-3 text-sm text-sky-100"
                x-data="{ remaining: @js($waitSeconds), ready: false }"
                x-init="const timer = setInterval(() => { remaining = Math.max(0, remaining - 1); if (remaining === 0) { ready = true; clearInterval(timer); } }, 1000)"
            >
                Esperando para consultar {{ $waitingPlatform }}. Nueva consulta en
                <span class="font-bold" x-text="remaining"></span>
                segundos.
                <button
                    type="button"
                    wire:click="retry"
                    x-show="ready"
                    x-cloak
                    class="ml-3 rounded-lg border border-white/10 bg-white/10 px-3 py-1 text-xs font-semibold text-white hover:bg-white/15"
                >
                    Reintentar
                </button>
            </div>
        </div>
    @elseif($error)
        <div class="rounded-2xl border border-amber-300/20 bg-zinc-950/25 backdrop-blur p-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <div class="text-sm text-amber-100/70">Consulta de plataforma</div>
                    <h3 class="text-white font-semibold">{{ $displaySection['title'] }}</h3>
                </div>
                <button type="button" wire:click="retry" class="rounded-xl border border-white/10 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/15">
                    Reintentar
                </button>
            </div>
            <div class="mt-3 rounded-xl border border-amber-300/20 bg-amber-300/10 px-4 py-3 text-sm text-amber-100">
                {{ $error }}
            </div>
        </div>
    @else
        @include('dashboard.partials.meta-table-section', [
            'section' => $displaySection,
            'customerName' => $customerName,
            'periodLabel' => $periodLabel,
            'livewireSort' => true,
        ])
    @endif
</div>
