@php
    $data = $dashboard;
@endphp

<x-app-layout>
    @vite('resources/js/general-leads-dashboard.js')

    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-xl font-semibold text-indigo-200">Dashboard General De Leads</h1>
            <a href="{{ route('dashboard') }}" class="inline-flex min-h-10 items-center rounded-xl border border-white/10 bg-white/10 px-4 py-2 text-sm text-white/80 hover:bg-white/15">Ver Dashboards</a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-[1800px] space-y-6 p-4 sm:p-6">
        <section class="grid grid-cols-1 gap-4 xl:grid-cols-12">
            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 p-4 backdrop-blur xl:col-span-3">
                <p class="text-sm text-white/60">Cliente Seleccionado</p>
                <h2 class="mt-2 text-2xl font-bold text-white">{{ $data['header']['customer'] }}</h2>
                <p class="mt-3 text-xs font-semibold text-white/50">Periodo Consultado</p>
                <p class="mt-1 text-sm text-white/80">{{ $data['header']['period'] }}</p>
            </div>

            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 p-4 backdrop-blur xl:col-span-9">
                <h2 class="sr-only">Filtros</h2>
                <form method="GET" action="{{ route('dashboard.general-leads') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
                    @foreach([
                        ['customer_id', 'Cliente', $data['filters']['customers']],
                        ['integration_id', 'Integración', $data['filters']['integrations']],
                        ['source', 'Source', $data['filters']['sources']],
                        ['campaign_origin', 'Origen', $data['filters']['origins']],
                        ['plataforma', 'Medio', $data['filters']['types']],
                        ['crm_state', 'Estado CRM', $data['filters']['crm_states']],
                        ['qualification', 'Calificación', $data['filters']['qualifications']],
                        ['lenguaje', 'Lenguaje', $data['filters']['languages']],
                        ['geo', 'Geo', $data['filters']['geos']],
                    ] as [$name, $label, $options])
                        <div>
                            <label for="{{ $name }}" class="mb-1 block text-sm text-white/70">{{ $label }}</label>
                            <select id="{{ $name }}" name="{{ $name }}" class="w-full rounded-xl border-white/10 bg-slate-900/60 text-white">
                                @foreach($options as $option)
                                    <option value="{{ $option['value'] }}" @selected($option['selected'])>{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                    <div>
                        <label for="from" class="mb-1 block text-sm text-white/70">Desde</label>
                        <input id="from" type="datetime-local" name="from" value="{{ $data['header']['from'] }}" max="{{ $data['header']['now'] }}" class="w-full rounded-xl border-white/10 bg-slate-900/60 text-white">
                    </div>
                    <div>
                        <label for="to" class="mb-1 block text-sm text-white/70">Hasta</label>
                        <input id="to" type="datetime-local" name="to" value="{{ $data['header']['to'] }}" max="{{ $data['header']['now'] }}" class="w-full rounded-xl border-white/10 bg-slate-900/60 text-white">
                    </div>
                    <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-4 xl:col-span-5">
                        <button class="min-h-11 rounded-xl border border-white/10 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/15">Filtrar</button>
                        <a href="{{ $data['filters']['clear_url'] }}" class="inline-flex min-h-11 items-center rounded-xl border border-white/10 bg-zinc-950/25 px-4 py-2 text-sm font-semibold text-white/80 hover:bg-white/10">Limpiar Filtros</a>
                    </div>
                </form>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-2xl font-bold text-white">Resumen</h2>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-7">
                @include('dashboard.general-leads.partials.summary-card', ['label' => 'Leads En El Periodo', 'value' => number_format($data['summary']['total'], 0, ',', '.'), 'secondaryLabel' => 'Periodo', 'secondaryValue' => $data['header']['period'], 'href' => $data['summary']['urls']['total']])
                @include('dashboard.general-leads.partials.summary-card', ['label' => 'Leads Gestionados', 'value' => number_format($data['summary']['managed'], 0, ',', '.'), 'href' => $data['summary']['urls']['managed']])
                @include('dashboard.general-leads.partials.summary-card', ['label' => 'Leads No Gestionados', 'value' => number_format($data['summary']['unmanaged'], 0, ',', '.'), 'href' => $data['summary']['urls']['unmanaged']])
                @include('dashboard.general-leads.partials.summary-card', ['label' => 'Leads No Efectivos', 'value' => number_format($data['summary']['not_effective'], 0, ',', '.'), 'href' => $data['summary']['urls']['not_effective']])
                @include('dashboard.general-leads.partials.summary-card', ['label' => 'Leads Efectivos', 'value' => number_format($data['summary']['effective'], 0, ',', '.'), 'href' => $data['summary']['urls']['effective']])
                @include('dashboard.general-leads.partials.summary-card', ['label' => 'Leads Venta', 'value' => number_format($data['summary']['sales']['count'], 0, ',', '.'), 'secondaryLabel' => 'Valor De Venta', 'secondaryValue' => $data['summary']['sales']['value'], 'href' => $data['summary']['urls']['sales']])
                @include('dashboard.general-leads.partials.summary-card', ['label' => 'Costo Total', 'value' => $data['costs']['total'], 'secondaryLabel' => 'Meta / Google', 'secondaryValue' => $data['costs']['meta'].' / '.$data['costs']['google']])
            </div>
            @if($data['summary']['missing_funnels'])
                <p class="text-sm text-amber-200/80">Funnels Canónicos No Encontrados: {{ implode(', ', $data['summary']['missing_funnels']) }}</p>
            @endif
        </section>

        <section class="space-y-4">
            <h2 class="text-2xl font-bold text-white">Costos De Campañas</h2>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                @include('dashboard.general-leads.partials.summary-card', ['label' => 'Costo Meta', 'value' => $data['costs']['meta']])
                @include('dashboard.general-leads.partials.summary-card', ['label' => 'Costo Google', 'value' => $data['costs']['google']])
                @include('dashboard.general-leads.partials.summary-card', ['label' => 'Costo Total', 'value' => $data['costs']['total']])
            </div>
            <p class="text-xs text-white/50">{{ $data['notes']['costs'] }}</p>
        </section>

        <section class="space-y-4">
            <h2 class="text-2xl font-bold text-white">Desglose De Leads</h2>
            @include('dashboard.general-leads.partials.breakdown-card', ['eyebrow' => 'Desglose', 'title' => 'Leads Por Source', 'breakdown' => $data['breakdowns']['source'], 'chartType' => 'donut', 'chartPayload' => $data['charts']['donuts']['source']])
            @include('dashboard.general-leads.partials.breakdown-card', ['eyebrow' => 'Desglose', 'title' => 'Leads Por Origen', 'breakdown' => $data['breakdowns']['origin'], 'chartType' => 'donut', 'chartPayload' => $data['charts']['donuts']['origin']])
            @include('dashboard.general-leads.partials.breakdown-card', ['eyebrow' => 'Desglose', 'title' => 'Leads Por Tipo', 'breakdown' => $data['breakdowns']['type'], 'chartType' => 'donut', 'chartPayload' => $data['charts']['donuts']['type']])
        </section>

        <section class="space-y-4">
            <h2 class="text-2xl font-bold text-white">Desglose De Calificación</h2>
            @include('dashboard.general-leads.partials.breakdown-card', ['eyebrow' => 'Calificación', 'title' => 'Calificación Por Source', 'breakdown' => $data['breakdowns']['source'], 'chartType' => 'stacked', 'chartPayload' => $data['charts']['stacks']['source']])
            @include('dashboard.general-leads.partials.breakdown-card', ['eyebrow' => 'Calificación', 'title' => 'Calificación Por Origen', 'breakdown' => $data['breakdowns']['origin'], 'chartType' => 'stacked', 'chartPayload' => $data['charts']['stacks']['origin']])
            @include('dashboard.general-leads.partials.breakdown-card', ['eyebrow' => 'Calificación', 'title' => 'Calificación Por Tipo', 'breakdown' => $data['breakdowns']['type'], 'chartType' => 'stacked', 'chartPayload' => $data['charts']['stacks']['type']])
        </section>

        <section class="space-y-4">
            <h2 class="text-2xl font-bold text-white">Funnel</h2>
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <section class="rounded-2xl border border-white/10 bg-zinc-950/25 p-4 backdrop-blur">
                    <h3 class="text-lg font-semibold text-white">Estado Actual Por Funnel</h3>
                    <div class="mt-4 space-y-2">
                        @forelse($data['funnels']['current'] as $row)
                            <a href="{{ $row['url'] }}" class="flex items-center justify-between rounded-xl border border-white/10 bg-white/5 px-4 py-3 transition hover:bg-white/10"><span class="text-sm text-white/80">{{ $row['name'] }}</span><span class="text-lg font-bold text-white">{{ number_format($row['total'], 0, ',', '.') }}</span></a>
                        @empty
                            <p class="py-6 text-center text-sm text-white/50">Sin Datos En El Periodo</p>
                        @endforelse
                    </div>
                </section>
                <section class="rounded-2xl border border-white/10 bg-zinc-950/25 p-4 backdrop-blur">
                    <h3 class="text-lg font-semibold text-white">Histórico Leads En El Funnel</h3>
                    <div class="mt-4 space-y-2">
                        @forelse($data['funnels']['history'] as $row)
                            <a href="{{ $row['url'] }}" class="flex items-center justify-between rounded-xl border border-white/10 bg-white/5 px-4 py-3 transition hover:bg-white/10"><span class="text-sm text-white/80">{{ $row['name'] }}</span><span class="text-lg font-bold text-white">{{ number_format($row['total'], 0, ',', '.') }}</span></a>
                        @empty
                            <p class="py-6 text-center text-sm text-white/50">Sin Datos En El Periodo</p>
                        @endforelse
                    </div>
                </section>
                <section class="rounded-2xl border border-white/10 bg-zinc-950/25 p-4 backdrop-blur">
                    <h3 class="text-lg font-semibold text-white">Histórico Diario De Leads En Funnel</h3>
                    <p class="mt-1 text-xs text-white/50">{{ $data['notes']['history'] }}</p>
                    <div class="mt-4 h-80 w-full" data-general-chart data-chart-type="funnel-daily" data-chart='@json($data['funnels']['daily'])'></div>
                </section>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-2xl font-bold text-white">Datos De Meta</h2>
            @foreach(['meta_campaigns', 'meta_ad_sets', 'meta_ads'] as $section)
                @include('dashboard.general-leads.partials.ads-table', ['table' => $data['ads'][$section]])
            @endforeach
        </section>

        <section class="space-y-4">
            <h2 class="text-2xl font-bold text-white">Datos De Google</h2>
            @foreach(['google_campaigns', 'google_ad_groups', 'google_ads'] as $section)
                @include('dashboard.general-leads.partials.ads-table', ['table' => $data['ads'][$section]])
            @endforeach
        </section>
    </div>
</x-app-layout>
