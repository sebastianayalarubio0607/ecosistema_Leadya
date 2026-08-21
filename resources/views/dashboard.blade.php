<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-indigo-200">Dashboards</h1>
    </x-slot>

    <div class="mx-auto max-w-7xl p-4 sm:p-6">
        <section class="space-y-4">
            <h2 class="text-2xl font-bold text-white">Selecciona Una Dashboard</h2>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <a href="{{ route('dashboard.gerencial-leads') }}" class="group rounded-2xl border border-white/10 bg-zinc-950/25 p-5 backdrop-blur transition hover:bg-white/5">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white/10 text-white/80">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor"><path d="M3 3a1 1 0 011-1h1a1 1 0 010 2H5v12h12v-1a1 1 0 112 0v1a1 1 0 01-1 1H4a1 1 0 01-1-1V3z" /><path d="M7 12a1 1 0 012 0v2a1 1 0 11-2 0v-2zM11 8a1 1 0 112 0v6a1 1 0 11-2 0V8zM15 10a1 1 0 112 0v4a1 1 0 11-2 0v-4z" /></svg>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-white">Dashboard Gerencial de Leads en LQ</h3>
                    <p class="mt-2 text-sm text-white/60">Vista Operativa Actual De Leads, Listados Y Métricas Existentes.</p>
                    <div class="mt-5 inline-flex min-h-10 items-center rounded-xl border border-white/10 bg-white/10 px-4 py-2 text-sm text-white/80 group-hover:bg-white/15">Abrir Dashboard</div>
                </a>

                <a href="{{ route('dashboard.general-leads') }}" class="group rounded-2xl border border-white/10 bg-zinc-950/25 p-5 backdrop-blur transition hover:bg-white/5">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white/10 text-white/80">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm3 4a1 1 0 011-1h1a1 1 0 011 1v6H7V7zm5 3a1 1 0 011-1h1a1 1 0 011 1v3h-3v-3zM4 13h2V9H4v4z" clip-rule="evenodd" /></svg>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-white">Dashboard General De Leads en LQ</h3>
                    <p class="mt-2 text-sm text-white/60">Vista General Responsive Con Costos, Funnels, Calificación Y Desgloses.</p>
                    <div class="mt-5 inline-flex min-h-10 items-center rounded-xl border border-white/10 bg-white/10 px-4 py-2 text-sm text-white/80 group-hover:bg-white/15">Abrir Dashboard</div>
                </a>
            </div>
        </section>
    </div>
</x-app-layout>
