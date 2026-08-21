@php
    $table = $section['table'] ?? null;
    $livewireSort = $livewireSort ?? false;
@endphp

<div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4" data-sortable-table-wrap>
    <div class="flex items-center justify-between gap-3">
        <div>
            <div class="text-sm text-white/60">Resumen por</div>
            <h3 class="text-white font-semibold">{{ $section['title'] }} - {{ $customerName }}</h3>
        </div>
        <div class="text-xs text-white/50">
            Periodo: <span class="text-white/80 font-semibold">{{ $periodLabel }}</span>
        </div>
    </div>

    @if (empty($table) || empty($table['enabled']))
        <div class="mt-3 text-sm text-white/60">
            {{ $table['note'] ?? ($section['empty_note'] ?? 'Sin datos en el periodo.') }}
        </div>
    @else
        @if (!empty($table['note']))
            <div class="mt-3 text-xs text-amber-200/80">{{ $table['note'] }}</div>
        @endif

        @if($livewireSort)
            <div data-sort-status class="mt-3 hidden flex items-center gap-2 rounded-xl border border-sky-300/20 bg-sky-300/10 px-4 py-2 text-sm font-semibold text-sky-100">
                <span class="h-2 w-2 animate-pulse rounded-full bg-sky-200"></span>
                Ordenando tabla con datos cargados...
            </div>
        @endif

        <div class="mt-3 overflow-x-auto rounded-xl border border-white/10">
            <table class="min-w-full text-xs" data-sortable-table>
                <thead class="bg-white/5 text-white/70">
                    <tr>
                        @foreach ($table['columns'] as $columnIndex => $column)
                            <th class="px-3 py-2 text-left font-semibold whitespace-nowrap" aria-sort="none">
                                @php
                                    $active = ($table['sort'] ?? '') === $column['key'];
                                    $direction = $active ? ($table['dir'] ?? 'desc') : 'none';
                                @endphp
                                <button type="button"
                                    class="inline-flex items-center gap-1 text-left font-semibold hover:text-white transition"
                                    data-sort-header
                                    data-column-index="{{ $columnIndex }}"
                                    data-sort-direction="{{ $direction }}">
                                    <span>{{ $column['label'] }}</span>
                                    <span class="text-white/35 text-[10px]" data-sort-icon>{{ $active ? strtoupper($direction) : 'sort' }}</span>
                                </button>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    @foreach ($table['rows'] as $row)
                        @php($rowUrl = $row['url'] ?? null)
                        <tr
                            @if($rowUrl)
                                role="link"
                                tabindex="0"
                                title="Ver leads relacionados"
                                onclick="window.location.href = @js($rowUrl)"
                                onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); window.location.href = @js($rowUrl); }"
                            @endif
                            class="hover:bg-white/5 {{ $rowUrl ? 'cursor-pointer focus:bg-white/5 focus:outline-none focus:ring-2 focus:ring-indigo-300/50' : '' }}"
                        >
                            @foreach ($table['columns'] as $column)
                                <td class="px-3 py-2 whitespace-nowrap text-white/80">
                                    {{ $row[$column['key']] ?? '-' }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if (!empty($section['footnote']))
            <div class="mt-2 text-[11px] text-white/40">{{ $section['footnote'] }}</div>
        @endif
    @endif
</div>
