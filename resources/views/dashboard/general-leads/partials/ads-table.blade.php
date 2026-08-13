@php
    $columns = ['name' => 'Nombre', 'cost' => 'Costo', 'impressions' => 'Impresiones', 'clicks' => 'Clicks', 'ctr' => 'CTR', 'cpc' => 'CPC', 'cpm' => 'CPM', 'conversions' => 'Conversiones Totales', 'roas' => 'ROAS', 'leads' => 'Leads', 'qualified_leads' => 'Leads Calificados', 'unqualified_leads' => 'Leads No Calificados', 'cpl' => 'CPL'];
    $livewireSort = $livewireSort ?? false;
@endphp

<section class="rounded-2xl border border-white/10 bg-zinc-950/25 p-4 backdrop-blur">
    <div class="mb-4">
        <p class="text-sm text-white/60">Resumen Por</p>
        <h3 class="text-lg font-semibold text-white">{{ $table['title'] }}</h3>
    </div>
    <div class="overflow-x-auto rounded-xl border border-white/10">
        <table class="min-w-full text-xs">
            <thead class="bg-white/5 text-white/70">
                <tr>
                    @foreach($columns as $key => $label)
                        @php
                            $nextDir = ($table['sort'] === $key && $table['dir'] === 'asc') ? 'desc' : 'asc';
                        @endphp
                        <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">
                            @if($livewireSort)
                                <button type="button" wire:click="sortBy('{{ $table['section'] }}', '{{ $key }}')" class="inline-flex items-center gap-1 hover:text-white">
                                    <span>{{ $label }}</span><span class="text-[10px] text-white/35">{{ $table['sort'] === $key ? strtoupper($table['dir']) : 'SORT' }}</span>
                                </button>
                            @else
                                <a href="{{ request()->fullUrlWithQuery(["sort[{$table['section']}]" => $key, "dir[{$table['section']}]" => $nextDir]) }}" class="inline-flex items-center gap-1 hover:text-white">
                                    <span>{{ $label }}</span><span class="text-[10px] text-white/35">{{ $table['sort'] === $key ? strtoupper($table['dir']) : 'SORT' }}</span>
                                </a>
                            @endif
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
