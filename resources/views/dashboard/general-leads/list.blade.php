@php
    $data = $dashboard;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-xl font-semibold text-indigo-200">{{ $data['title'] }}</h1>
            <a href="{{ $data['back_url'] }}" class="inline-flex min-h-10 items-center rounded-xl border border-white/10 bg-white/10 px-4 py-2 text-sm text-white/80 hover:bg-white/15">Volver A Dashboard</a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-[1800px] space-y-4 p-4 sm:p-6">
        <section class="rounded-2xl border border-white/10 bg-zinc-950/25 p-4 backdrop-blur">
            <h2 class="text-lg font-semibold text-white">Periodo Consultado</h2>
            <p class="mt-1 text-sm text-white/70">{{ $data['period'] }}</p>
        </section>

        <section class="rounded-2xl border border-white/10 bg-zinc-950/25 p-4 backdrop-blur">
            <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-lg font-semibold text-white">Datos Completos De Leads</h2>
                <p class="text-xs text-white/50">Incluye nombres de relaciones y todas las columnas de leads.</p>
            </div>

            <div class="overflow-x-auto rounded-xl border border-white/10">
                <table class="min-w-full text-xs">
                    <thead class="bg-white/5 text-white/70">
                        <tr>
                            @foreach($data['relation_columns'] as $label)
                                <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">{{ $label }}</th>
                            @endforeach
                            @foreach($data['lead_columns'] as $label)
                                <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                        @forelse($data['leads'] as $lead)
                            <tr class="text-white/80 hover:bg-white/5">
                                @foreach($data['relation_columns'] as $column => $label)
                                    @php
                                        $value = data_get($lead, $column);
                                        $display = ($value === null || $value === '') ? 'Sin Dato' : (string) $value;
                                    @endphp
                                    <td class="max-w-xs truncate whitespace-nowrap px-3 py-2" title="{{ $display }}">
                                        {{ $display }}
                                    </td>
                                @endforeach

                                @foreach($data['lead_columns'] as $column => $label)
                                    @php
                                        $value = data_get($lead, $column);

                                        if ($value instanceof \DateTimeInterface) {
                                            $display = $value->format('Y-m-d H:i:s');
                                        } elseif (is_bool($value)) {
                                            $display = $value ? 'Sí' : 'No';
                                        } elseif (is_array($value)) {
                                            $display = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                        } elseif ($value === null || $value === '') {
                                            $display = 'Sin Dato';
                                        } else {
                                            $display = (string) $value;
                                        }
                                    @endphp
                                    <td class="max-w-xs truncate whitespace-nowrap px-3 py-2" title="{{ $display }}">
                                        {{ $display }}
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($data['relation_columns']) + count($data['lead_columns']) }}" class="px-3 py-8 text-center text-white/50">Sin Leads Para Estos Filtros</td>
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
