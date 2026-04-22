@php($table = $section['table'] ?? null)

<div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4">
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

        <div class="mt-3 overflow-x-auto rounded-xl border border-white/10">
            <table class="min-w-full text-xs" data-sortable-table>
                <thead class="bg-white/5 text-white/70">
                    <tr>
                        @foreach ($table['columns'] as $columnIndex => $column)
                            <th class="px-3 py-2 text-left font-semibold whitespace-nowrap" aria-sort="none">
                                <button type="button"
                                    class="inline-flex items-center gap-1 text-left font-semibold hover:text-white transition"
                                    data-sort-header data-column-index="{{ $columnIndex }}">
                                    <span>{{ $column['label'] }}</span>
                                    <span class="text-white/35 text-[10px]" data-sort-icon>sort</span>
                                </button>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    @foreach ($table['rows'] as $row)
                        <tr class="hover:bg-white/5">
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
