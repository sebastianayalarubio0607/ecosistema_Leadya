@extends('meta.layout')

@section('title', 'Meta Insights')

@section('content')
    <div class="space-y-4">

        {{-- Alerts --}}
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/10 text-emerald-200 px-4 py-3">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-500/20 bg-rose-500/10 text-rose-200 px-4 py-3">
                <div class="font-semibold mb-1">Errores</div>
                <ul class="list-disc ml-5">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4">
            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
                <div>
                    <div class="text-sm text-white/60">Consulta y visualización</div>
                    <h2 class="text-xl font-semibold text-white">Insights por día (level=ad)</h2>
                    <div class="text-xs text-white/50">
                        Selecciona una fecha y presiona <span class="text-white/70 font-semibold">Consultar en Meta</span>
                        para traer datos de todas las cuentas activas.
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-stretch gap-2">
                    {{-- Form GET: filtra lo guardado en BD --}}
                    <form method="GET" action="{{ route('meta.insights.index') }}" class="flex items-end gap-2">
                        <div>
                            <label class="block text-xs text-white/60 mb-1">Fecha</label>
                            <input type="date" name="date" value="{{ $date }}"
                                   class="rounded-xl border border-white/10 bg-slate-900/60 text-white px-3 py-2" />
                        </div>
                        <button class="h-10 px-4 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
                            Ver guardado
                        </button>
                    </form>

                    {{-- Form POST: consulta Meta y guarda/actualiza (AHORA con date real) --}}
                    <form method="POST" action="{{ route('meta.insights.consult') }}" class="flex items-end gap-2">
                        @csrf
                        <div>
                            <label class="block text-xs text-white/60 mb-1">Fecha</label>
                            <input type="date" name="date" value="{{ $date }}"
                                   class="rounded-xl border border-white/10 bg-slate-900/60 text-white px-3 py-2" />
                        </div>
                        <button class="h-10 px-4 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 text-white border border-white/10">
                            Consultar en Meta
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Summary --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4">
                <div class="text-xs text-white/50">Filas (ads)</div>
                <div class="text-2xl font-semibold text-white">{{ (int)($summary->total_rows ?? 0) }}</div>
            </div>
            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4">
                <div class="text-xs text-white/50">Impresiones</div>
                <div class="text-2xl font-semibold text-white">{{ number_format((float)($summary->total_impressions ?? 0), 0, ',', '.') }}</div>
            </div>
            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4">
                <div class="text-xs text-white/50">Clicks</div>
                <div class="text-2xl font-semibold text-white">{{ number_format((float)($summary->total_clicks ?? 0), 0, ',', '.') }}</div>
            </div>
            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4">
                <div class="text-xs text-white/50">Spend</div>
                <div class="text-2xl font-semibold text-white">{{ number_format((float)($summary->total_spend ?? 0), 2, ',', '.') }}</div>
            </div>
        </div>

        {{-- Table --}}
        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4">
            <div class="w-full max-w-full overflow-x-auto rounded-xl border border-white/10 [scrollbar-gutter:stable]" data-sortable-table-wrap>
                <div class="hidden px-3 py-2 text-xs text-white/50" data-sort-status>Ordenando...</div>
                <table class="w-full min-w-[1700px] text-sm" data-sortable-table>
                    <thead class="bg-white/5 text-white/70">
                        <tr>
                            <x-sort-header :index="0" label="Clientes" />
                            <x-sort-header :index="1" label="Cuenta" />
                            <x-sort-header :index="2" label="Campaña" />
                            <x-sort-header :index="3" label="Conjunto" />
                            <x-sort-header :index="4" label="Anuncio" />

                            <x-sort-header :index="5" label="Imp." align="right" />
                            <x-sort-header :index="6" label="Reach" align="right" />
                            <x-sort-header :index="7" label="Freq" align="right" />
                            <x-sort-header :index="8" label="Clicks" align="right" />
                            <x-sort-header :index="9" label="Spend" align="right" />

                            <x-sort-header :index="10" label="CTR" align="right" />
                            <x-sort-header :index="11" label="uCTR" align="right" />
                            <x-sort-header :index="12" label="CPC" align="right" />
                            <x-sort-header :index="13" label="CPM" align="right" />

                            <x-sort-header :index="14" label="Fecha" />
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-white/10 text-white/80">
                        @forelse($items as $it)
                            @php
                                $customer = ($it->ad?->adSet?->campaign?->account?->customers ?? collect())->pluck('name')->implode(', ');
                                $customer = $customer !== ''
                                    ? $customer
                                    : ($it->ad?->adSet?->campaign?->account?->name ?? '—');
                                $accountName = $it->account_name ?? $it->ad?->adSet?->campaign?->account?->name ?? '—';
                            @endphp

                            <tr class="hover:bg-white/5">
                                <td class="px-3 py-2 whitespace-nowrap">{{ $customer }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $accountName }}</td>
                                <td class="px-3 py-2 max-w-[340px] truncate" title="{{ $it->campaign_name }}">{{ $it->campaign_name ?? '—' }}</td>
                                <td class="px-3 py-2 max-w-[340px] truncate" title="{{ $it->adset_name }}">{{ $it->adset_name ?? '—' }}</td>
                                <td class="px-3 py-2 max-w-[340px] truncate" title="{{ $it->ad_name }}">{{ $it->ad_name ?? '—' }}</td>

                                <td class="px-3 py-2 text-right">{{ number_format((float)($it->impressions ?? 0), 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format((float)($it->reach ?? 0), 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format((float)($it->frequency ?? 0), 2, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format((float)($it->clicks ?? 0), 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format((float)($it->spend ?? 0), 2, ',', '.') }}</td>

                                <td class="px-3 py-2 text-right">{{ number_format((float)($it->ctr ?? 0), 2, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format((float)($it->unique_ctr ?? 0), 2, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format((float)($it->cpc ?? 0), 2, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format((float)($it->cpm ?? 0), 2, ',', '.') }}</td>

                                <td class="px-3 py-2 whitespace-nowrap">{{ optional($it->date_stop)->format('Y-m-d') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="15" class="px-3 py-6 text-center text-white/60">No hay datos para esta fecha.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $items->links() }}
            </div>
        </div>

    </div>
@endsection
