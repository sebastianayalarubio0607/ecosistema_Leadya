<section class="rounded-2xl border border-white/10 bg-zinc-950/25 p-4 backdrop-blur">
    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm text-white/60">{{ $eyebrow }}</p>
            <h3 class="text-lg font-semibold text-white">{{ $title }}</h3>
        </div>
        <div class="text-xs text-white/50">Total: <span class="font-semibold text-white/80">{{ number_format($breakdown['totals']['total'], 0, ',', '.') }}</span></div>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-12 xl:items-center">
        <div class="xl:col-span-5">
            <div class="h-72 w-full" data-general-chart data-chart-type="{{ $chartType }}" data-chart='@json($chartPayload)'></div>
        </div>
        <div class="xl:col-span-7">
            <div class="overflow-x-auto rounded-xl border border-white/10">
                <table class="min-w-full text-xs">
                    <thead class="bg-white/5 text-white/70">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold">Nombre</th>
                            <th class="px-3 py-2 text-right font-semibold">Total</th>
                            <th class="px-3 py-2 text-right font-semibold">Calificados</th>
                            <th class="px-3 py-2 text-right font-semibold">No Calificados</th>
                            <th class="px-3 py-2 text-right font-semibold">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                        @forelse($breakdown['rows'] as $row)
                            <tr class="text-white/80 hover:bg-white/5">
                                <td class="px-3 py-2">{{ $row['name'] }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($row['total'], 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($row['qualified'], 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($row['unqualified'], 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right"><a href="{{ $row['url'] }}" class="inline-flex min-h-9 items-center rounded-lg border border-white/10 bg-white/10 px-3 py-1.5 text-white/80 hover:bg-white/15">Ver Lista</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-3 py-6 text-center text-white/50">Sin Datos En El Periodo</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-white/5 text-white">
                        <tr>
                            <td class="px-3 py-2 font-semibold">Totales</td>
                            <td class="px-3 py-2 text-right font-semibold">{{ number_format($breakdown['totals']['total'], 0, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right font-semibold">{{ number_format($breakdown['totals']['qualified'], 0, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right font-semibold">{{ number_format($breakdown['totals']['unqualified'], 0, ',', '.') }}</td>
                            <td class="px-3 py-2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</section>
