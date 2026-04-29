@extends('meta.layout')

@section('title', 'Google Ads Ad Groups')
@section('subtitle', 'Métricas diarias de grupos de anuncios por cliente')

@section('content')
    <div class="space-y-4">
        @include('google_ads.partials.sync-form', ['customers' => $customers, 'syncDate' => request('report_date')])

        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                <div class="md:col-span-3">
                    <label class="block mb-1 text-white/70">Cliente</label>
                    <select name="customer_id" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                        <option value="">Todos</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((string) request('customer_id') === (string) $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block mb-1 text-white/70">Fecha</label>
                    <input type="date" name="report_date" value="{{ request('report_date') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" />
                </div>
                <div class="md:col-span-3">
                    <label class="block mb-1 text-white/70">Campaign Name</label>
                    <input name="campaign_name" value="{{ request('campaign_name') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" />
                </div>
                <div class="md:col-span-2">
                    <label class="block mb-1 text-white/70">Ad Group Name</label>
                    <input name="ad_group_name" value="{{ request('ad_group_name') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" />
                </div>
                <div class="md:col-span-2">
                    <label class="block mb-1 text-white/70">Status</label>
                    <input name="ad_group_status" value="{{ request('ad_group_status') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" />
                </div>
                <div class="md:col-span-12 flex gap-2">
                    <button class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">Filtrar</button>
                    <a href="{{ route('google-ads.ad-groups.index') }}" class="px-4 py-2 rounded-xl bg-zinc-950/25 hover:bg-white/10 text-white border border-white/10">Limpiar</a>
                </div>
            </form>

            <div class="overflow-x-auto rounded-xl border border-white/10">
                <table class="min-w-full text-sm">
                    <thead class="bg-white/5 text-white/70">
                        <tr>
                            <th class="text-left px-3 py-2">Fecha</th>
                            <th class="text-left px-3 py-2">Cliente</th>
                            <th class="text-left px-3 py-2">Campaign</th>
                            <th class="text-left px-3 py-2">Ad Group</th>
                            <th class="text-left px-3 py-2">Status</th>
                            <th class="text-left px-3 py-2">Impresiones</th>
                            <th class="text-left px-3 py-2">Clicks</th>
                            <th class="text-left px-3 py-2">Conversiones</th>
                            <th class="text-left px-3 py-2">Costo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10 text-white/80">
                        @forelse($items as $item)
                            <tr class="hover:bg-white/5">
                                <td class="px-3 py-2">{{ $item->report_date?->format('Y-m-d') }}</td>
                                <td class="px-3 py-2">{{ $item->customer?->name ?: '—' }}</td>
                                <td class="px-3 py-2">{{ $item->campaign_name ?: '—' }}</td>
                                <td class="px-3 py-2">
                                    {{ $item->ad_group_name ?: '—' }}
                                    <div class="text-xs text-white/50">{{ $item->google_ad_group_id }}</div>
                                </td>
                                <td class="px-3 py-2">{{ $item->ad_group_status ?: '—' }}</td>
                                <td class="px-3 py-2">{{ number_format($item->impressions) }}</td>
                                <td class="px-3 py-2">{{ number_format($item->clicks) }}</td>
                                <td class="px-3 py-2">{{ number_format((float) $item->conversions, 2) }}</td>
                                <td class="px-3 py-2">{{ number_format((float) $item->cost, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-3 py-8 text-center text-white/60">No hay grupos de anuncios sincronizados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $items->links() }}</div>
        </div>
    </div>
@endsection
