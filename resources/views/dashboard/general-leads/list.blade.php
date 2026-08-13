@php
    $data = $dashboard;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-xl font-semibold text-indigo-200">{{ $data['title'] }}</h1>
            <div class="flex flex-wrap gap-2">
                <a href="{{ $data['export_url'] }}" class="inline-flex min-h-10 items-center rounded-xl border border-emerald-300/25 bg-emerald-500/15 px-4 py-2 text-sm font-semibold text-emerald-100 hover:bg-emerald-500/25">Descargar toda la tabla Excel</a>
                <a href="{{ $data['back_url'] }}" class="inline-flex min-h-10 items-center rounded-xl border border-white/10 bg-white/10 px-4 py-2 text-sm text-white/80 hover:bg-white/15">Volver A Dashboard</a>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-[2200px] space-y-4 p-4 sm:p-6">
        <section class="rounded-2xl border border-white/10 bg-zinc-950/25 p-4 backdrop-blur">
            <h2 class="text-lg font-semibold text-white">Periodo Consultado</h2>
            <p class="mt-1 text-sm text-white/70">{{ $data['period'] }}</p>
        </section>

        <section class="rounded-2xl border border-white/10 bg-zinc-950/25 p-4 backdrop-blur">
            <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-lg font-semibold text-white">Datos Completos De Leads</h2>
                <p class="text-xs text-white/50">Incluye los campos solicitados, sin Agent, Fields Custom ni Message.</p>
            </div>

            <div class="overflow-x-auto rounded-xl border border-white/10">
                <table class="min-w-max text-xs">
                    <thead class="bg-white/5 text-white/70">
                        <tr>
                            @foreach($data['columns'] as $column)
                                <th class="whitespace-nowrap px-3 py-2 text-left font-semibold">{{ $column['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                        @forelse($data['leads'] as $lead)
                            <tr class="text-white/80 hover:bg-white/5">
                                @foreach($data['columns'] as $column)
                                    @php
                                        $display = $lead[$column['key']] ?? '';
                                        $display = $display === '' ? 'Sin Dato' : $display;
                                    @endphp
                                    <td class="max-w-xs truncate whitespace-nowrap px-3 py-2" title="{{ $display }}">
                                        {{ $display }}
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($data['columns']) }}" class="px-3 py-8 text-center text-white/50">Sin Leads Para Estos Filtros</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $data['leads']->links() }}
            </div>
        </section>
    </div>
</x-app-layout>
