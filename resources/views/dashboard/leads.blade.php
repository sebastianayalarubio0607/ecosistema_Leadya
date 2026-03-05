<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-indigo-200">Dashboard Leads</h2>
        </div>
    </x-slot>

    <div class="p-6  mx-auto space-y-6">

        {{-- FILTROS --}}
        <div class="grid grid-cols-12 md:grid-cols-12 gap-4">

            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 col-span-3">
                <div class="text-sm text-white/60">Cliente seleccionado</div>
                <div class="text-lg font-semibold text-white">{{ $ui['header']['selected_customer_name'] }}</div>
                @if (!empty($ui['header']['selected_customer_id']))
                    <div class="text-xs text-white/50">customer_id: {{ $ui['header']['selected_customer_id'] }}</div>
                @endif
            </div>

            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 col-span-9">
                <form method="GET" action="{{ $ui['filters']['action'] }}"
                    class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">

                    @if (!empty($ui['filters']['integration_id']))
                        <input type="hidden" name="integration_id" value="{{ $ui['filters']['integration_id'] }}">
                    @endif

                    <div class="md:col-span-4">
                        <label class="block mb-1 text-white/70">Cliente</label>
                        <select name="customer_id"
                            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                            <option value="">-- Todos los clientes --</option>
                            @foreach ($ui['filters']['customer_options'] as $opt)
                                <option value="{{ $opt['value'] }}" @selected($opt['selected'])>
                                    {{ $opt['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-4">
                        <label class="block mb-1 text-white/70">Desde</label>
                        <input type="datetime-local" name="from" value="{{ $ui['filters']['from_value'] }}"
                            max="{{ $ui['filters']['now_max'] }}"
                            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" />

                    </div>

                    <div class="md:col-span-4">
                        <label class="block mb-1 text-white/70">Hasta</label>
                        <input type="datetime-local" name="to" value="{{ $ui['filters']['to_value'] }}"
                            max="{{ $ui['filters']['now_max'] }}"
                            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" />
                    </div>

                    <div class="md:col-span-3">
                        <label class="block mb-1 text-white/70">Fuente</label>
                        <select name="campaign_origin"
                            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                            <option value="">Todos</option>
                            @foreach ($ui['filters']['channel_options'] as $opt)
                                <option value="{{ $opt['value'] }}" @selected($opt['selected'])>
                                    {{ $opt['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-3">
                        <label class="block mb-1 text-white/70">Medio</label>
                        <select name="plataforma"
                            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                            <option value="">Todos</option>
                            @foreach ($ui['filters']['platform_options'] as $opt)
                                <option value="{{ $opt['value'] }}" @selected($opt['selected'])>
                                    {{ $opt['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-3 flex gap-2">
                        <button
                            class="w-full px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
                            Aplicar
                        </button>

                        <a href="{{ route('dashboard.leads') }}"
                            class="w-full text-center px-4 py-2 rounded-xl bg-zinc-950/25 hover:bg-white/10 text-white border border-white/10">
                            Limpiar
                        </a>
                    </div>
                </form>
            </div>

        </div>

        {{-- RESUMEN --}}
        <h2 class="text-2xl text-white font-bold">Resumen  -  {{ $ui['header']['selected_customer_name'] }} </h2>




        <div class="grid grid-cols-1 md:grid-cols-7 gap-4">

            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 col-span-1">
                <div class="text-sm text-white/60">Leads en el periodo seleccionado</div>
                <div class="text-3xl font-bold text-white">{{ $ui['summary']['count'] }}</div>
                Periodo: </br>
                <span class="text-xs text-white/80 font-semibold">{{ $ui['summary']['period_label'] }}</span>
            </div>

            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 col-span-1">
                <div class="text-sm text-white/60">Leads gestionados</div>
                <div class="text-3xl font-bold text-white">{{ $ui['summary']['managed'] }}</div>
            </div>

            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 col-span-1">
                <div class="text-sm text-white/60">Leads por gestionar</div>
                <div class="text-3xl font-bold text-white">{{ $ui['summary']['pending'] }}</div>
            </div>

            <div class="col-span-1">
                @if ($ui['special']['not_effective_url'])
                    <a href="{{ $ui['special']['not_effective_url'] }}"
                        class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 hover:bg-white/5 transition block">
                        <div class="text-sm text-white/60">Leads no Efectivos </div>
                        <div class="text-3xl font-bold text-white">{{ $ui['summary']['not_effective'] }}</div>
                        <div
                            class="mt-3 inline-flex items-center justify-between w-full px-3 py-2 rounded-xl bg-white/10 text-white/80 text-sm border border-white/10">
                            <span>Ver lista</span><span class="text-white/50">›</span>
                        </div>
                    </a>
                @else
                    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4">
                        <div class="text-sm text-white/60">Leads no Efectivos </div>
                        <div class="text-3xl font-bold text-white">{{ $ui['summary']['not_effective'] }}</div>
                        <div class="text-xs text-white/50 mt-1">{{ $ui['special']['not_effective_missing'] }}</div>
                    </div>
                @endif
            </div>


            <div class="col-span-1">
                @if ($ui['special']['qualified_url'])
                    <a href="{{ $ui['special']['qualified_url'] }}"
                        class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 hover:bg-white/5 transition block">
                        <div class="text-sm text-white/60">Leads calificados </div>
                        <div class="text-3xl font-bold text-white">{{ $ui['summary']['qualified'] }}</div>
                        <div
                            class="mt-3 inline-flex items-center justify-between w-full px-3 py-2 rounded-xl bg-white/10 text-white/80 text-sm border border-white/10">
                            <span>Ver lista</span><span class="text-white/50">›</span>
                        </div>
                    </a>
                @else
                    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4">
                        <div class="text-sm text-white/60">Leads calificados </div>
                        <div class="text-3xl font-bold text-white">{{ $ui['summary']['qualified'] }}</div>
                        <div class="text-xs text-white/50 mt-1">{{ $ui['special']['qualified_missing'] }}</div>
                    </div>
                @endif
            </div>

            <div class="col-span-1">
                @if ($ui['special']['sales_url'])
                    <a href="{{ $ui['special']['sales_url'] }}"
                        class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 hover:bg-white/5 transition block">
                        <div class="text-sm text-white/60">Leads con ventas </div>
                        <div class="text-3xl font-bold text-white">{{ $ui['summary']['sales'] }}</div>
                        <div class="text-sm text-white/60">Valor de la venta </div>
                        <div class="text-3xl font-bold text-white">
                            {{ $ui['summary']['sales_value_formatted'] }}
                        </div>
                        <div
                            class="mt-3 inline-flex items-center justify-between w-full px-3 py-2 rounded-xl bg-white/10 text-white/80 text-sm border border-white/10">
                            <span>Ver lista</span><span class="text-white/50">›</span>
                        </div>
                    </a>
                @else
                    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-2 ">
                        <div class="text-sm text-white/60">Leads con ventas </div>
                        <div class="text-3xl font-bold text-white">{{ $ui['summary']['sales'] }}</div>
                        <div class="text-xs text-white/50 mt-1">{{ $ui['special']['sales_missing'] }}</div>
                    </div>
                @endif


            </div>

            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 col-span-1">
                <div class="text-sm text-white/60">Costo (Spend) en el periodo seleccionado</div>
                <div class="text-3xl font-bold text-white">{{ $ui['summary']['spend_formatted'] }}</div>
            </div>

        </div>





        {{-- DESGLOSE --}}

        <h2 class="text-2xl text-white font-bold">Desglose  -  {{ $ui['header']['selected_customer_name'] }} </h2>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

            {{-- FUENTE --}}
            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 col-span-3">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <div class="text-sm text-white/60">Desglose</div>
                        <h3 class="text-white font-semibold">Por Fuente</h3>
                    </div>
                    <div class="text-xs text-white/50">
                        Total: <span
                            class="text-white/80 font-semibold">{{ $ui['donuts']['channels']['total'] }}</span>
                    </div>
                </div>

                @if (empty($ui['donuts']['channels']['pairs']))
                    <div class="text-sm text-white/60">Sin datos.</div>
                @else
                    <div class="flex items-center">
                        <div class=" py-12 grid grid-cols-12 gap-4 items-center">
                            <div class="col-span-12 md:col-span-4">
                                <div class="relative h-44">
                                    <canvas id="donutChannels" data-labels='@json($ui['donuts']['channels']['labels'])'
                                        data-values='@json($ui['donuts']['channels']['values'])'
                                        data-keys='@json($ui['donuts']['channels']['keys'])'
                                        data-base-url='@json($ui['donuts']['channels']['base_url'])'
                                        data-group-type="{{ $ui['donuts']['channels']['group_type'] }}"></canvas>
                                </div>
                            </div>

                            <div class="col-span-12 md:col-span-8">
                                <div class="text-xs text-white/50 mb-2">Leyenda (clic)</div>
                                <div id="legendChannels" class="space-y-2"></div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- MEDIO --}}
            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 col-span-3">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <div class="text-sm text-white/60">Desglose</div>
                        <h3 class="text-white font-semibold">Por Medio</h3>
                    </div>
                    <div class="text-xs text-white/50">
                        Total: <span
                            class="text-white/80 font-semibold">{{ $ui['donuts']['platforms']['total'] }}</span>
                    </div>
                </div>

                @if (empty($ui['donuts']['platforms']['pairs']))
                    <div class="text-sm text-white/60">Sin datos.</div>
                @else
                    <div class="flex items-center">
                        <div class=" py-12 grid grid-cols-12 gap-4 items-center">
                            <div class=" col-span-12 md:col-span-4">
                                <div class="relative h-44">
                                    <canvas id="donutPlatforms" data-labels='@json($ui['donuts']['platforms']['labels'])'
                                        data-values='@json($ui['donuts']['platforms']['values'])'
                                        data-keys='@json($ui['donuts']['platforms']['keys'])'
                                        data-base-url='@json($ui['donuts']['platforms']['base_url'])'
                                        data-group-type="{{ $ui['donuts']['platforms']['group_type'] }}"></canvas>
                                </div>
                            </div>

                            <div class="col-span-12 md:col-span-8">
                                <div class="text-xs text-white/50 mb-2">Leyenda (clic)</div>
                                <div id="legendPlatforms" class="space-y-2"></div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Leads por Funnel --}}
            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 col-span-3">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-white font-semibold">Estado actual de Leads por Funnel</h3>
                    <div class="text-xs text-white/50">Total:
                        <span class="text-white/80 font-semibold">{{ $ui['totals']['total_leads'] }}</span>
                    </div>
                </div>

                {{-- ✅ Funnel vertical (embudo hacia abajo) --}}
                @php
                    $widths = ['w-full', 'w-11/12', 'w-10/12', 'w-9/12', 'w-8/12'];
                @endphp

                <div class="relative max-w-xl mx-auto py-2">
                    <div class="absolute left-4 top-1 bottom-4 w-px bg-white/10"></div>

                    <div class="space-y-2">
                        @foreach ($ui['cards']['funnels'] as $card)
                            <div class="relative flex justify-center">
                                <div class="absolute left-4 top-1/2 -translate-y-1/2 flex items-center">
                                    <div class="h-2.5 w-2.5 rounded-full bg-white/30"></div>
                                    <div class="h-px w-6 bg-white/10"></div>
                                </div>
                                <a href="{{ $card['url'] }}"
                                    class="{{ $widths[$loop->index] ?? end($widths) }} block">
                                    <div
                                        class="rounded-2xl border border-white/10 bg-zinc-950/25 p-4 hover:bg-white/5 transition text-center ">
                                        <div class="">
                                            <span class=" text-sm text-white/80 font-semibold truncate">
                                                {{ $card['name'] }}: <span
                                                    class="text-2xl font-extrabold text-white leading-none">{{ $card['count'] }}
                                                    - {{ $card['pct'] }}%
                                                </span></span>
                                        </div>
                                        <div class=" text-sm text-white/80 font-semibold truncate">
                                        </div>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Leads por Funnel --}}
            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 col-span-3">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-white font-semibold">Historico Leads en el Funnel </h3>
                    <div class="text-xs text-white/50">
                        <span class="text-white/80 font-semibold"></span>
                    </div>
                </div>

                {{-- ✅ Funnel vertical (embudo hacia abajo) --}}
                @php
                    $widths = ['w-full', 'w-11/12', 'w-10/12', 'w-9/12', 'w-8/12'];
                @endphp

                <div class="relative max-w-xl mx-auto py-2">
                    <div class="absolute left-4 top-1 bottom-4 w-px bg-white/10"></div>

                    <div class="space-y-4">
                        @foreach ($ui['cards']['funnels_history'] as $card)
                            <div class="relative flex justify-center">
                                <div class="absolute left-4 top-1/2 -translate-y-1/2 flex items-center">
                                    <div class="h-2.5 w-2.5 rounded-full bg-white/30"></div>
                                    <div class="h-px w-6 bg-white/10"></div>
                                </div>
                                <a href="{{ $card['url'] }}"
                                    class="{{ $widths[$loop->index] ?? end($widths) }} block">
                                    <div
                                        class="rounded-2xl border border-white/10 bg-zinc-950/25 p-2 hover:bg-white/5 transition text-center">
                                        <div class="text-2xl font-extrabold text-white leading-none">
                                            {{ $card['count'] }} -{{ $card['pct'] }}%</div>
                                        <div class=" text-sm text-white/80 font-semibold truncate">{{ $card['name'] }}
                                        </div>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

        </div>



        <h2 class="text-2xl text-white font-bold">Datos de Meta - {{ $ui['header']['selected_customer_name'] }}
        </h2>

        {{-- Campañas Meta (MetaAdInsight) --}}
        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <div class="text-sm text-white/60">Resumen por</div>
                    <h3 class="text-white font-semibold">Campaña de Meta  -  
                        {{ $ui['header']['selected_customer_name'] }}</h3>
                </div>
                <div class="text-xs text-white/50">
                    Periodo: <span class="text-white/80 font-semibold">{{ $ui['summary']['period_label'] }}</span>
                </div>
            </div>

            @php($t = $ui['tables']['meta_campaigns'] ?? null)

            @if (empty($t) || empty($t['enabled']))
                <div class="mt-3 text-sm text-white/60">
                    {{ $t['note'] ?? 'Sin datos de campañas en el periodo.' }}
                </div>
            @else
                @if (!empty($t['note']))
                    <div class="mt-3 text-xs text-amber-200/80">{{ $t['note'] }}</div>
                @endif

                <div class="mt-3 overflow-x-auto rounded-xl border border-white/10">
                    <table class="min-w-full text-xs">
                        <thead class="bg-white/5 text-white/70">
                            <tr>
                                @foreach ($t['columns'] as $col)
                                    <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">
                                        {{ $col['label'] }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10">
                            @foreach ($t['rows'] as $row)
                                <tr class="hover:bg-white/5">
                                    @foreach ($t['columns'] as $col)
                                        <td class="px-3 py-2 whitespace-nowrap text-white/80">
                                            {{ $row[$col['key']] ?? '-' }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-2 text-[11px] text-white/40">
                    Mostrando hasta 200 campañas ordenadas por costo.
                </div>
            @endif



        </div>

        {{-- Grupo de anuncios Meta (MetaAdSet) --}}
        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <div class="text-sm text-white/60">Resumen por</div>
                    <h3 class="text-white font-semibold">Grupo de anuncios de Meta  -  
                        {{ $ui['header']['selected_customer_name'] }}</h3>
                </div>
                <div class="text-xs text-white/50">
                    Periodo: <span class="text-white/80 font-semibold">{{ $ui['summary']['period_label'] }}</span>
                </div>
            </div>

            @php($t = $ui['tables']['meta_ad_sets'] ?? null)

            @if (empty($t) || empty($t['enabled']))
                <div class="mt-3 text-sm text-white/60">
                    {{ $t['note'] ?? 'Sin datos de grupos de anuncios en el periodo.' }}
                </div>
            @else
                @if (!empty($t['note']))
                    <div class="mt-3 text-xs text-amber-200/80">{{ $t['note'] }}</div>
                @endif

                <div class="mt-3 overflow-x-auto rounded-xl border border-white/10">
                    <table class="min-w-full text-xs">
                        <thead class="bg-white/5 text-white/70">
                            <tr>
                                @foreach ($t['columns'] as $col)
                                    <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">
                                        {{ $col['label'] }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10">
                            @foreach ($t['rows'] as $row)
                                <tr class="hover:bg-white/5">
                                    @foreach ($t['columns'] as $col)
                                        <td class="px-3 py-2 whitespace-nowrap text-white/80">
                                            {{ $row[$col['key']] ?? '-' }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-2 text-[11px] text-white/40">
                    Mostrando hasta 200 grupos ordenados por costo.
                </div>
            @endif
        </div>

        {{-- Anuncios Meta (MetaAd) --}}
        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <div class="text-sm text-white/60">Resumen por </div>
                    <h3 class="text-white font-semibold">Anuncio de Meta  -  
                        {{ $ui['header']['selected_customer_name'] }}</h3>
                </div>
                <div class="text-xs text-white/50">
                    Periodo: <span class="text-white/80 font-semibold">{{ $ui['summary']['period_label'] }}</span>
                </div>
            </div>

            @php($t = $ui['tables']['meta_ads'] ?? null)

            @if (empty($t) || empty($t['enabled']))
                <div class="mt-3 text-sm text-white/60">
                    {{ $t['note'] ?? 'Sin datos de anuncios en el periodo.' }}
                </div>
            @else
                @if (!empty($t['note']))
                    <div class="mt-3 text-xs text-amber-200/80">{{ $t['note'] }}</div>
                @endif

                <div class="mt-3 overflow-x-auto rounded-xl border border-white/10">
                    <table class="min-w-full text-xs">
                        <thead class="bg-white/5 text-white/70">
                            <tr>
                                @foreach ($t['columns'] as $col)
                                    <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">
                                        {{ $col['label'] }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10">
                            @foreach ($t['rows'] as $row)
                                <tr class="hover:bg-white/5">
                                    @foreach ($t['columns'] as $col)
                                        <td class="px-3 py-2 whitespace-nowrap text-white/80">
                                            {{ $row[$col['key']] ?? '-' }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-2 text-[11px] text-white/40">
                    Mostrando hasta 200 anuncios ordenados por costo.
                </div>
            @endif
        </div>

    </div>

    {{-- Chart.js + click en segmento y leyenda --}}
    <script>
        (() => {
            const COLORS = ["#8B5CF6", "#22C55E", "#06B6D4", "#F59E0B", "#EC4899"];
            const charts = window.__LEADSYA_CHARTS || (window.__LEADSYA_CHARTS = {});

            function loadChartJs() {
                if (window.Chart) return Promise.resolve();
                return new Promise((resolve, reject) => {
                    const s = document.createElement("script");
                    s.src = "https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js";
                    s.onload = resolve;
                    s.onerror = reject;
                    document.head.appendChild(s);
                });
            }

            function goToGroup(baseUrl, groupType, groupId) {
                if (!baseUrl || !groupType || !groupId) return;
                if (groupId === "__OTHER__") return;
                const url = new URL(baseUrl, window.location.origin);
                // ✅ Agrega/actualiza parámetros para ir al listado detallado
                url.searchParams.set("group_type", groupType);
                url.searchParams.set("group_id", groupId);
                window.location.href = url.toString();
            }

            function renderLegend(containerId, labels, values, keys, baseUrl, groupType) {
                const el = document.getElementById(containerId);
                if (!el) return;
                el.innerHTML = "";

                labels.forEach((label, i) => {
                    const key = keys[i];
                    const row = document.createElement("div");
                    row.className = "flex items-center justify-between gap-3 text-sm rounded-lg px-2 py-1";

                    if (key !== "__OTHER__") {
                        row.classList.add("cursor-pointer", "hover:bg-white/5");
                        row.addEventListener("click", () => goToGroup(baseUrl, groupType, key));
                    }

                    const left = document.createElement("div");
                    left.className = "flex items-center gap-2 min-w-0";

                    const dot = document.createElement("span");
                    dot.className = "h-3 w-3 rounded-sm shrink-0";
                    dot.style.background = COLORS[i % COLORS.length];

                    const name = document.createElement("span");
                    name.className = "text-white/85 truncate";
                    name.textContent = label;

                    const right = document.createElement("span");
                    right.className = "text-white/70 font-semibold";
                    right.textContent = String(values[i] ?? 0);

                    left.appendChild(dot);
                    left.appendChild(name);
                    row.appendChild(left);
                    row.appendChild(right);
                    el.appendChild(row);
                });
            }

            function buildDonut(canvasId, legendId) {
                const canvas = document.getElementById(canvasId);
                if (!canvas) return;

                const labels = JSON.parse(canvas.dataset.labels || "[]");
                const values = JSON.parse(canvas.dataset.values || "[]");
                const keys = JSON.parse(canvas.dataset.keys || "[]");
                const baseUrl = JSON.parse(canvas.dataset.baseUrl || '""');
                const groupType = canvas.dataset.groupType;

                if (!labels.length || !values.length) return;

                if (charts[canvasId]) {
                    charts[canvasId].destroy();
                    delete charts[canvasId];
                }

                renderLegend(legendId, labels, values, keys, baseUrl, groupType);

                const ctx = canvas.getContext("2d");
                charts[canvasId] = new Chart(ctx, {
                    type: "doughnut",
                    data: {
                        labels,
                        datasets: [{
                            data: values,
                            backgroundColor: labels.map((_, i) => COLORS[i % COLORS.length]),
                            borderColor: "rgba(255,255,255,.08)",
                            borderWidth: 1,
                            hoverOffset: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: "62%",
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        onClick: (_, elements) => {
                            if (!elements || !elements.length) return;
                            const idx = elements[0].index;
                            goToGroup(baseUrl, groupType, keys[idx]);
                        }
                    }
                });
            }

            async function init() {
                await loadChartJs();
                buildDonut("donutChannels", "legendChannels");
                buildDonut("donutPlatforms", "legendPlatforms");
            }

            // ✅ Evita "fechas futuras" sin bloquear el presente
            function nowLocalValue() {
                const d = new Date();
                const tz = d.getTimezoneOffset() * 60000;
                return new Date(d.getTime() - tz).toISOString().slice(0, 16);
            }

            function setupDatetimeMax() {
                const max = nowLocalValue();
                const inputs = document.querySelectorAll(
                    'input[type="datetime-local"][name="from"], input[type="datetime-local"][name="to"]');
                inputs.forEach((el) => {
                    el.max = max;
                });
            }

            init();
            setupDatetimeMax();
            setInterval(setupDatetimeMax, 60 * 1000);
        })();
    </script>
</x-app-layout>