<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4 text-white">
            <div><h1 class="text-2xl font-semibold">Centro de alertas</h1><p class="text-sm text-white/70">Incidencias, avisos y confirmaciones de tu equipo</p></div>
            <nav class="flex flex-wrap gap-2" aria-label="Secciones de alertas">
                <a wire:navigate href="{{ route('alerts.index') }}" class="rounded-xl px-4 py-2 border border-white/10 {{ $section === 'inbox' ? 'bg-white/20' : 'bg-white/5 hover:bg-white/10' }}">Mi bandeja</a>
                @can('manage-alerts')
                    <a wire:navigate href="{{ route('alerts.metrics') }}" class="rounded-xl px-4 py-2 border border-white/10 {{ $section === 'metrics' ? 'bg-white/20' : 'bg-white/5 hover:bg-white/10' }}">Impresiones y leads</a>
                    <a wire:navigate href="{{ route('alerts.rules') }}" class="rounded-xl px-4 py-2 border border-white/10 {{ $section === 'rules' ? 'bg-white/20' : 'bg-white/5 hover:bg-white/10' }}">Configuración</a>
                    <a wire:navigate href="{{ route('alerts.history') }}" class="rounded-xl px-4 py-2 border border-white/10 {{ $section === 'history' ? 'bg-white/20' : 'bg-white/5 hover:bg-white/10' }}">Historial general</a>
                @endcan
            </nav>
        </div>
    </x-slot>
    <div class="p-4 md:p-6 text-white">
        @if (!$available)
            <div class="rounded-2xl border border-white/10 bg-slate-950/30 p-6">El centro de alertas todavía no está disponible. Contacta al administrador para su habilitación.</div>
        @elseif ($section === 'metrics')
            <livewire:alerts.metric-monitors />
        @elseif ($section === 'rules')
            <livewire:alerts.alert-rules />
        @elseif ($section === 'history')
            <livewire:alerts.alert-history />
        @else
            <livewire:alerts.alert-inbox />
        @endif
    </div>
</x-app-layout>
