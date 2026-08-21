@php
    $columns = ['name' => 'Nombre', 'cost' => 'Costo', 'impressions' => 'Impresiones', 'clicks' => 'Clicks', 'ctr' => 'CTR', 'cpc' => 'CPC', 'cpm' => 'CPM', 'conversions' => 'Conversiones Totales', 'roas' => 'ROAS', 'leads' => 'Leads en LQ', 'qualified_leads' => 'Leads en LQ Calificados', 'unqualified_leads' => 'Leads en LQ No Calificados', 'cpl' => 'CPL'];
    $livewireSort = $livewireSort ?? false;
@endphp

<section class="rounded-2xl border border-white/10 bg-zinc-950/25 p-4 backdrop-blur" data-sortable-table-wrap>
    <div class="mb-4">
        <p class="text-sm text-white/60">Resumen Por</p>
        <h3 class="text-lg font-semibold text-white">{{ $table['title'] }}</h3>
    </div>
    @if($livewireSort)
        <div data-sort-status class="mb-3 hidden flex items-center gap-2 rounded-xl border border-sky-300/20 bg-sky-300/10 px-4 py-2 text-sm font-semibold text-sky-100">
            <span class="h-2 w-2 animate-pulse rounded-full bg-sky-200"></span>
            Ordenando tabla con datos cargados...
        </div>
    @endif
    <div class="overflow-x-auto rounded-xl border border-white/10">
        <table class="min-w-full text-xs" data-sortable-table>
            <thead class="bg-white/5 text-white/70">
                <tr>
                    @foreach($columns as $key => $label)
                        @php
                            $active = ($table['sort'] ?? '') === $key;
                            $direction = $active ? ($table['dir'] ?? 'desc') : 'none';
                        @endphp
                        <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">
                            <button type="button" data-sort-header data-column-index="{{ $loop->index }}" data-sort-direction="{{ $direction }}" class="inline-flex items-center gap-1 hover:text-white">
                                <span>{{ $label }}</span><span class="text-[10px] text-white/35" data-sort-icon>{{ $active ? strtoupper($direction) : 'SORT' }}</span>
                            </button>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-white/10">
                @forelse($table['rows'] as $row)
                    @php($rowUrl = $row['url'] ?? null)
                    <tr
                        @if($rowUrl)
                            role="link"
                            tabindex="0"
                            title="Ver leads relacionados"
                            onclick="window.location.href = @js($rowUrl)"
                            onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); window.location.href = @js($rowUrl); }"
                        @endif
                        class="text-white/80 hover:bg-white/5 {{ $rowUrl ? 'cursor-pointer focus:bg-white/5 focus:outline-none focus:ring-2 focus:ring-indigo-300/50' : '' }}"
                    >
                        @foreach(array_keys($columns) as $key)
                            <td class="px-3 py-2 whitespace-nowrap">{{ $row[$key] }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($columns) }}" class="px-3 py-6 text-center text-white/50">Sin Datos En El Periodo</td></tr>
                @endforelse
            </tbody>
            <tfoot class="bg-white/5 text-white">
                <tr>
                    @foreach(array_keys($columns) as $key)
                        <td class="px-3 py-2 whitespace-nowrap font-semibold">{{ $table['totals'][$key] }}</td>
                    @endforeach
                </tr>
            </tfoot>
        </table>
    </div>
</section>
