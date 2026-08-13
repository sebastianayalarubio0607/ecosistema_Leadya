<div wire:init="load">
    @if(! $loaded)
        <section class="rounded-2xl border border-white/10 bg-zinc-950/25 p-4 backdrop-blur">
            <div class="mb-4">
                <p class="text-sm text-white/60">Resumen Por</p>
                <h3 class="text-lg font-semibold text-white">{{ $table['title'] }}</h3>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-8 text-center text-sm text-white/60">
                Consultando datos directamente en la plataforma...
            </div>
        </section>
    @elseif($waiting)
        <section class="rounded-2xl border border-sky-300/20 bg-zinc-950/25 p-4 backdrop-blur">
            <div class="mb-4">
                <p class="text-sm text-sky-100/70">Límite De Consultas</p>
                <h3 class="text-lg font-semibold text-white">{{ $table['title'] }}</h3>
            </div>
            <div
                class="rounded-xl border border-sky-300/20 bg-sky-300/10 px-4 py-3 text-sm text-sky-100"
                x-data="{ remaining: @js($waitSeconds) }"
                x-init="const timer = setInterval(() => { remaining = Math.max(0, remaining - 1); if (remaining === 0) { clearInterval(timer); $wire.retry(); } }, 1000)"
            >
                Esperando para consultar {{ $waitingPlatform }}. Nueva consulta en
                <span class="font-bold" x-text="remaining"></span>
                segundos.
            </div>
        </section>
    @else
        @if($error)
            <section class="rounded-2xl border border-amber-300/20 bg-zinc-950/25 p-4 backdrop-blur">
                <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-amber-100/70">Consulta De Plataforma</p>
                        <h3 class="text-lg font-semibold text-white">{{ $table['title'] }}</h3>
                    </div>
                    <button type="button" wire:click="retry" class="inline-flex min-h-10 items-center rounded-xl border border-white/10 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/15">
                        Reintentar
                    </button>
                </div>
                <p class="rounded-xl border border-amber-300/20 bg-amber-300/10 px-4 py-3 text-sm text-amber-100">
                    {{ $error }}
                </p>
            </section>
        @else
            @include('dashboard.general-leads.partials.ads-table', ['table' => $table, 'livewireSort' => true])
        @endif
    @endif
</div>
