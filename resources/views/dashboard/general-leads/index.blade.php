<x-app-layout>
    @vite('resources/js/general-leads-dashboard.js')

    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-xl font-semibold text-indigo-200">Dashboard General De Leads en LQ</h1>
            <a href="{{ route('dashboard') }}" class="inline-flex min-h-10 items-center rounded-xl border border-white/10 bg-white/10 px-4 py-2 text-sm text-white/80 hover:bg-white/15">Ver Dashboards</a>
        </div>
    </x-slot>

    <livewire:general-leads-dashboard />
</x-app-layout>
