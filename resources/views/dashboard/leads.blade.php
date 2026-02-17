<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-indigo-200">Dashboard Leads</h2>
        </div>
    </x-slot>

    @php
        $customerId = $customerId ?? null;

        $selectedChannel = request('campaign_origin', '');
        $selectedPlatform = request('plataforma', '');

        $channels = $metric['channels'] ?? [];
        $platforms = $metric['platforms'] ?? [];

        $totalLeads = (int) ($metric['count'] ?? 0);

        // Cards CRM State
        $crmCards = [];
        $crmCards[] = [
            'id' => '__NULL__',
            'name' => 'Sin Estado',
            'count' => (int) ($metric['pending_count'] ?? 0),
        ];
        foreach ($metric['crm_states'] ?? [] as $s) {
            $crmCards[] = $s;
        }

        // Cards Qualification
        $qualCards = [];
        foreach ($metric['qualifications'] ?? [] as $q) {
            $qualCards[] = [
                'id' => $q['id'] === null ? '__NULL__' : $q['id'],
                'name' => $q['name'],
                'count' => $q['count'],
            ];
        }

        // ✅ NUEVO: Cards Funnel
        $funnelCards = [];
        $funnelCards[] = [
            'id' => '__NULL__',
            'name' => 'Por Responder',
            'count' => (int) ($metric['no_funnel_count'] ?? 0),
        ];
        foreach ($metric['funnels'] ?? [] as $f) {
            $funnelCards[] = $f;
        }

        // ✅ NUEVO: Calificados / Ventas
        $qualifiedCount = (int) ($metric['qualified_count'] ?? 0);
        $qualifiedFunnelId = $metric['qualified_funnel_id'] ?? null;

        $salesCount = (int) ($metric['sales_count'] ?? 0);
        $salesFunnelId = $metric['sales_funnel_id'] ?? null;

        // Donut helper
        $prepareDonut = function (array $data, string $dimension) {
            if (empty($data)) {
                return ['keys' => [], 'labels' => [], 'values' => [], 'pairs' => [], 'total' => 0];
            }

            arsort($data);
            $top = array_slice($data, 0, 4, true);
            $rest = array_slice($data, 4, null, true);

            if (!empty($rest)) {
                $top['__OTHER__'] = array_sum($rest);
            }

            $keys = array_keys($top);
            $values = array_values($top);

            $labelMap = function ($k) use ($dimension) {
                if ($k === '__OTHER__') {
                    return 'Otros';
                }
                if ($k === '__NULL__') {
                    return $dimension === 'campaign_origin' ? 'Sin Fuente' : 'Sin Medio';
                }
                return $k;
            };

            $labels = array_map($labelMap, $keys);

            $pairs = [];
            foreach ($keys as $i => $k) {
                $pairs[] = ['key' => $k, 'label' => $labels[$i], 'count' => (int) $values[$i]];
            }

            return [
                'keys' => $keys,
                'labels' => $labels,
                'values' => $values,
                'pairs' => $pairs,
                'total' => array_sum($values),
            ];
        };

        $channelsDonut = $prepareDonut($channels, 'campaign_origin');
        $platformsDonut = $prepareDonut($platforms, 'plataforma');

        $baseParamsCommon = \Illuminate\Support\Arr::except(request()->query(), [
            'crm_state',
            'qualification',
            'campaign_origin',
            'plataforma',
            'group_type',
            'group_id',
            'page',
        ]);

        $baseForChannels = route(
            'dashboard.leads.list',
            \Illuminate\Support\Arr::except(request()->query(), [
                'campaign_origin',
                'crm_state',
                'qualification',
                'group_type',
                'group_id',
                'page',
            ]),
        );

        $baseForPlatforms = route(
            'dashboard.leads.list',
            \Illuminate\Support\Arr::except(request()->query(), [
                'plataforma',
                'crm_state',
                'qualification',
                'group_type',
                'group_id',
                'page',
            ]),
        );
    @endphp

    <div class="p-6  mx-auto space-y-6">

        {{-- FILTROS --}}
        <div class="grid grid-cols-12 md:grid-cols-12 gap-4">


            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 col-span-3">
                <div class="text-sm text-white/60">Cliente seleccionado</div>
                <div class="text-lg font-semibold text-white">{{ $selectedCustomer?->name ?? 'Todos los clientes' }}
                </div>
                @if ($selectedCustomer)
                    <div class="text-xs text-white/50">customer_id: {{ $selectedCustomer->id }}</div>
                @endif
            </div>


            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 col-span-9">
                <form method="GET" action="{{ route('dashboard.leads') }}"
                    class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">

                    @if (request()->has('integration_id'))
                        <input type="hidden" name="integration_id" value="{{ request('integration_id') }}">
                    @endif

                    <div class="md:col-span-6">
                        <label class="block mb-1 text-white/70">Cliente</label>
                        <select name="customer_id"
                            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                            <option value="">-- Todos los clientes --</option>
                            @foreach ($customers as $c)
                                <option value="{{ $c->id }}" @selected((string) $customerId === (string) $c->id)>
                                    {{ $c->name }} (ID: {{ $c->id }})
                                </option>
                            @endforeach
                        </select>
                    </div>




                    <div class="md:col-span-3">
                        <label class="block mb-1 text-white/70">Fuente</label>
                        <select name="campaign_origin"
                            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                            <option value="">Todos</option>
                            @foreach (array_keys($channels) as $ch)
                                @php $label = ($ch === '__NULL__') ? 'Sin Fuente' : $ch; @endphp
                                <option value="{{ $ch }}" @selected((string) $selectedChannel === (string) $ch)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-3">
                        <label class="block mb-1 text-white/70">Medio</label>
                        <select name="plataforma"
                            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                            <option value="">Todos</option>
                            @foreach (array_keys($platforms) as $pl)
                                @php $label = ($pl === '__NULL__') ? 'Sin Medio' : $pl; @endphp
                                <option value="{{ $pl }}" @selected((string) $selectedPlatform === (string) $pl)>
                                    {{ $label }}
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
        <div class="grid grid-cols-1 md:grid-cols-6 gap-4">



            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4">
                <div class="text-sm text-white/60">Leads en últimos 7 días</div>
                <div class="text-3xl font-bold text-white">{{ $metric['count'] ?? 0 }}</div>
                <div class="text-xs text-white/50">Calculado: {{ $metric['calculated_at'] ?? '-' }}</div>
            </div>

            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4">
                <div class="text-sm text-white/60">Leads gestionados</div>
                <div class="text-3xl font-bold text-white">{{ $metric['managed_count'] ?? 0 }}</div>
            </div>

            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4">
                <div class="text-sm text-white/60">Leads por gestionar</div>
                <div class="text-3xl font-bold text-white">{{ $metric['pending_count'] ?? 0 }}</div>
            </div>


            @php
                $baseClick = \Illuminate\Support\Arr::except(request()->query(), [
                    'crm_state',
                    'qualification',
                    'group_type',
                    'group_id',
                    'page',
                ]);
                $qualifiedUrl = $qualifiedFunnelId
                    ? route(
                        'dashboard.leads.list',
                        array_merge($baseClick, ['group_type' => 'funnel', 'group_id' => $qualifiedFunnelId]),
                    )
                    : null;
                $salesUrl = $salesFunnelId
                    ? route(
                        'dashboard.leads.list',
                        array_merge($baseClick, ['group_type' => 'funnel', 'group_id' => $salesFunnelId]),
                    )
                    : null;
            @endphp
            <div class="">
                @if ($qualifiedUrl)
                    <a href="{{ $qualifiedUrl }}"
                        class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 hover:bg-white/5 transition block">
                        <div class="text-sm text-white/60">Leads calificados </div>
                        <div class="text-3xl font-bold text-white">{{ $qualifiedCount }}</div>
                        <div
                            class="mt-3 inline-flex items-center justify-between w-full px-3 py-2 rounded-xl bg-white/10 text-white/80 text-sm border border-white/10">
                            <span>Ver lista</span><span class="text-white/50">›</span>
                        </div>
                    </a>
                @else
                    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4">
                        <div class="text-sm text-white/60">Leads calificados </div>
                        <div class="text-3xl font-bold text-white">{{ $qualifiedCount }}</div>
                        <div class="text-xs text-white/50 mt-1">No existe un funnel llamado "Calificados".</div>
                    </div>
                @endif

            </div>
            <div class="">
                @if ($salesUrl)
                    <a href="{{ $salesUrl }}"
                        class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 hover:bg-white/5 transition block">
                        <div class="text-sm text-white/60">Leads con ventas </div>
                        <div class="text-3xl font-bold text-white">{{ $salesCount }}</div>
                        <div
                            class="mt-3 inline-flex items-center justify-between w-full px-3 py-2 rounded-xl bg-white/10 text-white/80 text-sm border border-white/10">
                            <span>Ver lista</span><span class="text-white/50">›</span>
                        </div>
                    </a>
                @else
                    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4">
                        <div class="text-sm text-white/60">Leads con ventas </div>
                        <div class="text-3xl font-bold text-white">{{ $salesCount }}</div>
                        <div class="text-xs text-white/50 mt-1">No existe un funnel llamado "Ventas".</div>
                    </div>
                @endif

            </div>



            @php
                $spendValue = (float) ($metric['spend'] ?? 0);
            @endphp
            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4">
                <div class="text-sm text-white/60">Costo (Spend) últimos 7 días</div>
                <div class="text-3xl font-bold text-white">$ {{ number_format($spendValue, 2, ',', '.') }}</div>
            </div>


        </div>



        {{-- DESGLOSE --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

            {{-- FUENTE --}}
            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 col-span-3">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <div class="text-sm text-white/60">Desglose</div>
                        <h3 class="text-white font-semibold">Por Fuente</h3>
                    </div>
                    <div class="text-xs text-white/50">
                        Total: <span class="text-white/80 font-semibold">{{ $channelsDonut['total'] }}</span>
                    </div>
                </div>

                @if (empty($channelsDonut['pairs']))
                    <div class="text-sm text-white/60">Sin datos.</div>
                @else
                    <div class="grid grid-cols-12 gap-4 items-center">

                        @foreach ($channelsDonut['pairs'] as $row)
                            @php
                                $isOther = $row['key'] === '__OTHER__';
                                $url = $isOther
                                    ? '#'
                                    : route(
                                        'dashboard.leads.list',
                                        array_merge(
                                            \Illuminate\Support\Arr::except(request()->query(), [
                                                'campaign_origin',
                                                'crm_state',
                                                'qualification',
                                                'group_type',
                                                'group_id',
                                                'page',
                                            ]),
                                            ['group_type' => 'campaign_origin', 'group_id' => $row['key']],
                                        ),
                                    );
                            @endphp
                        @endforeach


                        <div class="col-span-12 md:col-span-4">
                            <div class="relative h-44">
                                <canvas id="donutChannels" data-labels='@json($channelsDonut['labels'])'
                                    data-values='@json($channelsDonut['values'])' data-keys='@json($channelsDonut['keys'])'
                                    data-base-url='@json($baseForChannels)'
                                    data-group-type="campaign_origin"></canvas>
                            </div>
                        </div>

                        <div class="col-span-12 md:col-span-8">
                            <div class="text-xs text-white/50 mb-2">Leyenda (clic)</div>
                            <div id="legendChannels" class="space-y-2"></div>
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
                        Total: <span class="text-white/80 font-semibold">{{ $platformsDonut['total'] }}</span>
                    </div>
                </div>

                @if (empty($platformsDonut['pairs']))
                    <div class="text-sm text-white/60">Sin datos.</div>
                @else
                    <div class="grid grid-cols-12 gap-4 items-center">

                        @foreach ($platformsDonut['pairs'] as $row)
                            @php
                                $isOther = $row['key'] === '__OTHER__';
                                $url = $isOther
                                    ? '#'
                                    : route(
                                        'dashboard.leads.list',
                                        array_merge(
                                            \Illuminate\Support\Arr::except(request()->query(), [
                                                'plataforma',
                                                'crm_state',
                                                'qualification',
                                                'group_type',
                                                'group_id',
                                                'page',
                                            ]),
                                            ['group_type' => 'plataforma', 'group_id' => $row['key']],
                                        ),
                                    );
                            @endphp
                        @endforeach


                        <div class="col-span-12 md:col-span-4">
                            <div class="relative h-44">
                                <canvas id="donutPlatforms" data-labels='@json($platformsDonut['labels'])'
                                    data-values='@json($platformsDonut['values'])'
                                    data-keys='@json($platformsDonut['keys'])'
                                    data-base-url='@json($baseForPlatforms)'
                                    data-group-type="plataforma"></canvas>
                            </div>
                        </div>

                        <div class="col-span-12 md:col-span-8">
                            <div class="text-xs text-white/50 mb-2">Leyenda (clic)</div>
                            <div id="legendPlatforms" class="space-y-2"></div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- ✅ NUEVO: Leads por Funnel (cards clicables) --}}
            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 col-span-6">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-white font-semibold">Leads por Funnel</h3>
                    <div class="text-xs text-white/50">Total: <span
                            class="text-white/80 font-semibold">{{ $totalLeads }}</span></div>
                </div>

                <div class="flex gap-2 overflow-x-auto pb-2">
                    @foreach ($funnelCards as $card)
                        @php
                            $count = (int) ($card['count'] ?? 0);
                            $pct = $totalLeads > 0 ? (int) round(($count / $totalLeads) * 100) : 0;

                            $url = route(
                                'dashboard.leads.list',
                                array_merge(
                                    \Illuminate\Support\Arr::except(request()->query(), [
                                        'crm_state',
                                        'qualification',
                                        'group_type',
                                        'group_id',
                                        'page',
                                    ]),
                                    ['group_type' => 'funnel', 'group_id' => $card['id']],
                                ),
                            );
                        @endphp

                        <a href="{{ $url }}" class="w-[20%] block">
                            <div class="text-center text-xs text-white/60 mb-2">{{ $pct }}%</div>

                            <div
                                class="rounded-2xl border border-white/10 bg-zinc-950/25 p-3 hover:bg-white/5 transition">
                                <div class="h-10 rounded-xl bg-white/10 flex items-center px-3">
                                    <div class="text-2xl font-extrabold text-white leading-none">{{ $count }}
                                    </div>
                                </div>

                                <div class="mt-2 text-sm text-white/80 font-semibold truncate">{{ $card['name'] }}
                                </div>

                                <!--    <div
                                    class="mt-3 inline-flex items-center justify-between w-full px-3 py-2 rounded-xl bg-white/10 text-white/80 text-sm border border-white/10">
                                    <span>Ver por Funnel</span>
                                    <span class="text-white/50">›</span>
                                </div>-->
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>


        </div>

        <h2 class="text-white font-bold texto-5xl ">Datos del CRM</h2>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

            {{-- Leads por Cualificación (cards clicables) --}}
            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 ">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-white font-semibold">Leads agrupados por Cualificación</h3>
                    <div class="text-xs text-white/50">Total: <span
                            class="text-white/80 font-semibold">{{ $totalLeads }}</span></div>
                </div>

                <div class="flex gap-3 overflow-x-auto pb-2">
                    @foreach ($qualCards as $card)
                        @php
                            $count = (int) ($card['count'] ?? 0);
                            $pct = $totalLeads > 0 ? (int) round(($count / $totalLeads) * 100) : 0;

                            $url = route(
                                'dashboard.leads.list',
                                array_merge(
                                    \Illuminate\Support\Arr::except(request()->query(), [
                                        'crm_state',
                                        'qualification',
                                        'group_type',
                                        'group_id',
                                        'page',
                                    ]),
                                    ['group_type' => 'qualification', 'group_id' => $card['id']],
                                ),
                            );
                        @endphp

                        <a href="{{ $url }}" class="w-[20%] block">
                            <div class="text-center text-xs text-white/60 mb-2">{{ $pct }}%</div>

                            <div
                                class="rounded-2xl border border-white/10 bg-zinc-950/25 p-3 hover:bg-white/5 transition">
                                <div class="h-10 rounded-xl bg-white/10 flex items-center px-3">
                                    <div class="text-2xl font-extrabold text-white leading-none">{{ $count }}
                                    </div>
                                </div>

                                <div class="mt-2 text-sm text-white/80 font-semibold truncate">{{ $card['name'] }}
                                </div>

                                <!--  <div
                                    class="mt-3 inline-flex items-center justify-between w-full px-3 py-2 rounded-xl bg-white/10 text-white/80 text-sm border border-white/10">
                                    <span>Ver por Cualificación</span>
                                    <span class="text-white/50">›</span>
                                </div>-->
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Leads por Estado (cards clicables) --}}
            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-white font-semibold">Leads agrupados por Estado del CRM</h3>
                    <div class="text-xs text-white/50">Total: <span
                            class="text-white/80 font-semibold">{{ $totalLeads }}</span></div>
                </div>

                <div class="flex gap-3 overflow-x-auto pb-2">
                    @foreach ($crmCards as $card)
                        @php
                            $count = (int) ($card['count'] ?? 0);
                            $pct = $totalLeads > 0 ? (int) round(($count / $totalLeads) * 100) : 0;

                            $url = route(
                                'dashboard.leads.list',
                                array_merge(
                                    \Illuminate\Support\Arr::except(request()->query(), [
                                        'crm_state',
                                        'qualification',
                                        'group_type',
                                        'group_id',
                                        'page',
                                    ]),
                                    ['group_type' => 'crm_state', 'group_id' => $card['id']],
                                ),
                            );
                        @endphp

                        <a href="{{ $url }}" class="w-[20%] block">
                            <div class="text-center text-xs text-white/60 mb-2">{{ $pct }}%</div>

                            <div
                                class="rounded-2xl border border-white/10 bg-zinc-950/25 p-3 hover:bg-white/5 transition">
                                <div class="h-10 rounded-xl bg-white/10 flex items-center px-3">
                                    <div class="text-2xl font-extrabold text-white leading-none">{{ $count }}
                                    </div>
                                </div>

                                <div class="mt-2 text-sm text-white/80 font-semibold truncate">{{ $card['name'] }}
                                </div>

                                <!--    <div
                                    class="mt-3 inline-flex items-center justify-between w-full px-3 py-2 rounded-xl bg-white/10 text-white/80 text-sm border border-white/10">
                                    <span>Ver por Estado</span>
                                    <span class="text-white/50">›</span>
                                </div>-->
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
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

            document.addEventListener("DOMContentLoaded", init);
        })();
    </script>
</x-app-layout>
