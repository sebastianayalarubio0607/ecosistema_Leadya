<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-indigo-200">Dashboard Leads</h2>
        </div>
    </x-slot>

    <div class="p-6 mx-auto space-y-6">
        <div class="grid grid-cols-12 md:grid-cols-12 gap-4">
            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 col-span-3">
                <div class="text-sm text-white/60">Cliente seleccionado</div>
                <div class="text-lg font-semibold text-white">{{ $ui['header']['selected_customer_name'] }}</div>
                @if (!empty($ui['header']['selected_customer_id']))
                    <div class="text-xs text-white/50">customer_id: {{ $ui['header']['selected_customer_id'] }}</div>
                @endif
            </div>

            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 col-span-9">
                <form method="GET" action="{{ $ui['filters']['action'] }}" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                    @if (!empty($ui['filters']['integration_id']))
                        <input type="hidden" name="integration_id" value="{{ $ui['filters']['integration_id'] }}">
                    @endif

                    <div class="md:col-span-4">
                        <label class="block mb-1 text-white/70">Cliente</label>
                        <select name="customer_id" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                            <option value="">-- Todos los clientes --</option>
                            @foreach ($ui['filters']['customer_options'] as $option)
                                <option value="{{ $option['value'] }}" @selected($option['selected'])>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-4">
                        <label class="block mb-1 text-white/70">Desde</label>
                        <input type="datetime-local" name="from" value="{{ $ui['filters']['from_value'] }}"
                            max="{{ $ui['filters']['now_max'] }}"
                            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    </div>

                    <div class="md:col-span-4">
                        <label class="block mb-1 text-white/70">Hasta</label>
                        <input type="datetime-local" name="to" value="{{ $ui['filters']['to_value'] }}"
                            max="{{ $ui['filters']['now_max'] }}"
                            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    </div>

                    <div class="md:col-span-3">
                        <label class="block mb-1 text-white/70">Fuente</label>
                        <select name="campaign_origin" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                            <option value="">Todos</option>
                            @foreach ($ui['filters']['channel_options'] as $option)
                                <option value="{{ $option['value'] }}" @selected($option['selected'])>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-3">
                        <label class="block mb-1 text-white/70">Medio</label>
                        <select name="plataforma" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                            <option value="">Todos</option>
                            @foreach ($ui['filters']['platform_options'] as $option)
                                <option value="{{ $option['value'] }}" @selected($option['selected'])>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-3 flex gap-2">
                        <button class="w-full px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
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

        <h2 class="text-2xl text-white font-bold">Resumen - {{ $ui['header']['selected_customer_name'] }}</h2>

        <div class="grid grid-cols-1 md:grid-cols-7 gap-4">
            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 col-span-1">
                <div class="text-sm text-white/60">Leads en el periodo seleccionado</div>
                <div class="text-3xl font-bold text-white">{{ $ui['summary']['count'] }}</div>
                <span class="text-3xl font-bold text-white">Periodo:</span> <br>
                <span class="text-xs text-white/80 font-semibold">{{ $ui['summary']['period_label'] }}</span>
            </div>

            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 col-span-1">
                <div class="text-sm text-white/60">Leads gestionados</div>
                <div class="text-3xl font-bold text-white">{{ $ui['summary']['managed'] }}</div>
            </div>

            <a href="{{ $ui['summary']['pending_url'] }}"
                class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 col-span-1 hover:bg-white/5 transition block">
                <div class="text-sm text-white/60">Por gestionar</div>
                <div class="text-3xl font-bold text-white">{{ $ui['summary']['pending'] }}</div>
                <div
                    class="mt-3 inline-flex items-center justify-between w-full px-3 py-2 rounded-xl bg-white/10 text-white/80 text-sm border border-white/10">
                    <span>Ver lista</span><span class="text-white/50">&rsaquo;</span>
                </div>
            </a>

            @foreach ($ui['summary_cards'] as $card)
                @include('dashboard.partials.summary-action-card', ['card' => $card])
            @endforeach

            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 col-span-1">
                <div class="text-sm text-white/60">Costo (Spend) en el periodo seleccionado</div>
                <div class="text-3xl font-bold text-white">{{ $ui['summary']['spend_formatted'] }}</div>
            </div>
        </div>

        <h2 class="text-2xl text-white font-bold">Desglose - {{ $ui['header']['selected_customer_name'] }}</h2>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
            @include('dashboard.partials.donut-card', [
                'title' => 'Por Fuente',
                'donut' => $ui['donuts']['channels'],
                'canvasId' => 'donutChannels',
                'legendId' => 'legendChannels',
            ])

            @include('dashboard.partials.donut-card', [
                'title' => 'Por Medio',
                'donut' => $ui['donuts']['platforms'],
                'canvasId' => 'donutPlatforms',
                'legendId' => 'legendPlatforms',
            ])

            @include('dashboard.partials.funnel-stack', [
                'title' => 'Estado actual de Leads por Funnel',
                'cards' => $ui['cards']['funnels'],
                'totalLabel' => 'Total',
                'totalValue' => $ui['totals']['total_leads'],
                'stackGap' => 'space-y-2',
                'cardPadding' => 'p-4',
                'variant' => 'default',
            ])

            @include('dashboard.partials.funnel-stack', [
                'title' => 'Historico Leads en el Funnel',
                'cards' => $ui['cards']['funnels_history'],
                'totalLabel' => 'Total',
                'totalValue' => $ui['totals']['total_leads'],
                'stackGap' => 'space-y-4',
                'cardPadding' => 'p-2',
                'variant' => 'history',
            ])
        </div>

        @php($historyDailyChart = $ui['charts']['funnels_history_daily'] ?? ['labels' => [], 'datasets' => []])
        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 w-full">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-white font-semibold">Historico Leads en el Funnel por dia</h3>
                <div class="text-xs text-white/50">
                    Total:
                    <span class="text-white/80 font-semibold">{{ $ui['totals']['total_leads'] }}</span>
                </div>
            </div>
            <div class="h-80 w-full">
                <canvas id="funnelHistoryDailyChart"
                        data-labels='@json($historyDailyChart['labels'])'
                        data-datasets='@json($historyDailyChart['datasets'])'></canvas>
            </div>
        </div>

        @php($opportunitiesSalesChart = $ui['charts']['opportunities_sales_daily'] ?? ['labels' => [], 'datasets' => []])
        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 w-full">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-white font-semibold">Oportunidades vs Ventas por dia</h3>
                <div class="text-xs text-white/50">
                    Total:
                    <span class="text-white/80 font-semibold">{{ $ui['totals']['total_leads'] }}</span>
                </div>
            </div>
            <div class="h-80 w-full">
                <canvas id="opportunitiesSalesDailyChart"
                        data-labels='@json($opportunitiesSalesChart['labels'])'
                        data-datasets='@json($opportunitiesSalesChart['datasets'])'></canvas>
            </div>
        </div>

        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 w-full">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-white font-semibold">Tendencia Oportunidades vs Ventas por dia</h3>
                <div class="text-xs text-white/50">
                    Total:
                    <span class="text-white/80 font-semibold">{{ $ui['totals']['total_leads'] }}</span>
                </div>
            </div>
            <div class="h-80 w-full">
                <canvas id="opportunitiesSalesDailyLineChart"
                        data-labels='@json($opportunitiesSalesChart['labels'])'
                        data-datasets='@json($opportunitiesSalesChart['datasets'])'></canvas>
            </div>
        </div>

        <h2 class="text-2xl text-white font-bold">Datos de Meta - {{ $ui['header']['selected_customer_name'] }}</h2>

        @foreach ($ui['meta_sections'] as $section)
            @include('dashboard.partials.meta-table-section', [
                'section' => $section,
                'customerName' => $ui['header']['selected_customer_name'],
                'periodLabel' => $ui['summary']['period_label'],
            ])
        @endforeach
    </div>

    <script>
        (() => {
            const COLORS = ["#8B5CF6", "#22C55E", "#06B6D4", "#F59E0B", "#EC4899"];
            const charts = window.__LEADSYA_CHARTS || (window.__LEADSYA_CHARTS = {});

            function loadECharts() {
                if (window.echarts) return Promise.resolve();

                return new Promise((resolve, reject) => {
                    const script = document.createElement("script");
                    script.src = "https://cdn.jsdelivr.net/npm/echarts@5.5.1/dist/echarts.min.js";
                    script.onload = resolve;
                    script.onerror = reject;
                    document.head.appendChild(script);
                });
            }

            function goToGroup(baseUrl, groupType, groupId) {
                if (!baseUrl || !groupType || !groupId || groupId === "__OTHER__") {
                    return;
                }

                const url = new URL(baseUrl, window.location.origin);
                url.searchParams.set("group_type", groupType);
                url.searchParams.set("group_id", groupId);
                window.location.href = url.toString();
            }

            function renderLegend(containerId, labels, values, keys, baseUrl, groupType) {
                const container = document.getElementById(containerId);
                if (!container) return;

                container.innerHTML = "";

                labels.forEach((label, index) => {
                    const key = keys[index];
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
                    dot.style.background = COLORS[index % COLORS.length];

                    const name = document.createElement("span");
                    name.className = "text-white/85 truncate";
                    name.textContent = label;

                    const right = document.createElement("span");
                    right.className = "text-white/70 font-semibold";
                    right.textContent = String(values[index] ?? 0);

                    left.appendChild(dot);
                    left.appendChild(name);
                    row.appendChild(left);
                    row.appendChild(right);
                    container.appendChild(row);
                });
            }

            function chartContainer(element) {
                if (!element) return null;
                if (element.tagName.toLowerCase() !== "canvas") return element;

                const container = document.createElement("div");
                container.id = element.id;
                container.className = "h-full w-full";
                Object.keys(element.dataset).forEach((key) => {
                    container.dataset[key] = element.dataset[key];
                });
                element.replaceWith(container);

                return container;
            }

            function disposeChart(chartId) {
                if (!charts[chartId]) return;

                if (typeof charts[chartId].dispose === "function") {
                    charts[chartId].dispose();
                } else if (typeof charts[chartId].destroy === "function") {
                    charts[chartId].destroy();
                }
                delete charts[chartId];
            }

            function chartTextColor(alpha = ".75") {
                return `rgba(255,255,255,${alpha})`;
            }

            function buildDonut(canvasId, legendId) {
                const canvas = chartContainer(document.getElementById(canvasId));
                if (!canvas || !window.echarts) return;

                const labels = JSON.parse(canvas.dataset.labels || "[]");
                const values = JSON.parse(canvas.dataset.values || "[]");
                const keys = JSON.parse(canvas.dataset.keys || "[]");
                const baseUrl = JSON.parse(canvas.dataset.baseUrl || '""');
                const groupType = canvas.dataset.groupType;

                if (!labels.length || !values.length) return;

                disposeChart(canvasId);

                renderLegend(legendId, labels, values, keys, baseUrl, groupType);

                charts[canvasId] = echarts.init(canvas, null, { renderer: "canvas" });
                charts[canvasId].setOption({
                    color: COLORS,
                    tooltip: {
                        trigger: "item",
                        backgroundColor: "rgba(15,23,42,.96)",
                        borderColor: "rgba(255,255,255,.12)",
                        textStyle: { color: "#fff" },
                        formatter: "{b}<br/><strong>{c}</strong> ({d}%)"
                    },
                    legend: { show: false },
                    series: [{
                        
                       type: 'pie',
      radius: ['40%', '70%'],
      avoidLabelOverlap: false,
      padAngle: 0,
      itemStyle: {
        borderRadius: 0
      },
                        label: { show: false },
                        emphasis: {
                            scale: true,
                            scaleSize: 2
                        },
                        data: labels.map((label, index) => ({
                            name: label,
                            value: values[index] ?? 0
                        }))
                    }]
                });
                charts[canvasId].on("click", (params) => {
                    goToGroup(baseUrl, groupType, keys[params.dataIndex]);
                });
            }

            function buildFunnelHistoryDailyChart(canvasId = "funnelHistoryDailyChart", stacked = true, type = "bar") {
                const canvas = chartContainer(document.getElementById(canvasId));
                if (!canvas || !window.echarts) return;

                const labels = JSON.parse(canvas.dataset.labels || "[]");
                const rawDatasets = JSON.parse(canvas.dataset.datasets || "[]");
                if (!labels.length || !rawDatasets.length) return;

                const chartId = canvas.id;
                disposeChart(chartId);

                const datasets = rawDatasets.map((dataset, index) => {
                    const color = COLORS[index % COLORS.length];

                    return {
                        type,
                        label: dataset.label,
                        name: dataset.label,
                        data: dataset.data,
                        stack: stacked ? "total" : null,
                        smooth: type === "line",
                        symbol: type === "line" ? "circle" : "none",
                        symbolSize: type === "line" ? 7 : 0,
                        barMaxWidth: 42,
                        lineStyle: {
                            width: 3,
                            color
                        },
                        itemStyle: {
                            color,
                            borderRadius: type === "bar" ? [0, 5, 0, 0] : 0
                        },
                        areaStyle: type === "line" ? {
                            opacity: 0.18,
                            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                                { offset: 0, color },
                                { offset: 1, color: "rgba(255,255,255,0)" }
                            ])
                        } : undefined,
                        emphasis: {
                            focus: "series"
                        }
                    };
                });

                charts[chartId] = echarts.init(canvas, null, { renderer: "canvas" });
                charts[chartId].setOption({
                    color: COLORS,
                    tooltip: {
                        trigger: "axis",
                        backgroundColor: "rgba(15,23,42,.96)",
                        borderColor: "rgba(255,255,255,.12)",
                        textStyle: { color: "#fff" },
                        axisPointer: {
                            type: type === "line" ? "line" : "shadow",
                            lineStyle: { color: "rgba(255,255,255,.25)" },
                            shadowStyle: { color: "rgba(255,255,255,.06)" }
                        }
                    },
                    legend: {
                        top: 0,
                        right: 0,
                        icon: "roundRect",
                        textStyle: { color: chartTextColor(".78") }
                    },
                    grid: {
                        left: 36,
                        right: 18,
                        top: 48,
                        bottom: 32,
                        containLabel: true
                    },
                    xAxis: {
                        type: "category",
                        boundaryGap: type !== "line",
                        data: labels,
                        axisLabel: { color: chartTextColor(".68") },
                        axisLine: { lineStyle: { color: "rgba(255,255,255,.12)" } },
                        axisTick: { show: false },
                        splitLine: { show: false }
                    },
                    yAxis: {
                        type: "value",
                        minInterval: 1,
                        axisLabel: { color: chartTextColor(".68") },
                        axisLine: { show: false },
                        axisTick: { show: false },
                        splitLine: { lineStyle: { color: "rgba(255,255,255,.08)" } }
                    },
                    series: datasets
                });
            }

            function resizeCharts() {
                Object.values(charts).forEach((chart) => {
                    if (chart && typeof chart.resize === "function") {
                        chart.resize();
                    }
                });
            }

            function nowLocalValue() {
                const date = new Date();
                const timezoneOffset = date.getTimezoneOffset() * 60000;
                return new Date(date.getTime() - timezoneOffset).toISOString().slice(0, 16);
            }

            function setupDatetimeMax() {
                const max = nowLocalValue();
                const inputs = document.querySelectorAll('input[type="datetime-local"][name="from"], input[type="datetime-local"][name="to"]');
                inputs.forEach((input) => {
                    input.max = max;
                });
            }

            function parseSortableValue(value) {
                const raw = String(value ?? "").trim();
                if (!raw || raw === "-") {
                    return { type: "empty", value: "" };
                }

                const digitsOnlyCandidate = raw.replace(/[$%\s.,-]/g, "");
                const looksNumeric = /^\d+$/.test(digitsOnlyCandidate);
                const numericCandidate = raw
                    .replace(/[^\d,.-]/g, "")
                    .replace(/\.(?=\d{3}(\D|$))/g, "")
                    .replace(",", ".");
                const number = Number.parseFloat(numericCandidate);

                if (looksNumeric && numericCandidate && Number.isFinite(number)) {
                    return { type: "number", value: number };
                }

                return { type: "text", value: raw.toLocaleLowerCase("es") };
            }

            function compareSortableValues(left, right, direction) {
                if (left.type === "empty" && right.type !== "empty") return 1;
                if (right.type === "empty" && left.type !== "empty") return -1;

                const multiplier = direction === "asc" ? 1 : -1;

                if (left.type === "number" && right.type === "number") {
                    return (left.value - right.value) * multiplier;
                }

                return String(left.value).localeCompare(String(right.value), "es", {
                    numeric: true,
                    sensitivity: "base"
                }) * multiplier;
            }

            function setupSortableTables() {
                document.querySelectorAll("[data-sortable-table]").forEach((table) => {
                    const tbody = table.tBodies[0];
                    if (!tbody) return;

                    Array.from(tbody.rows).forEach((row, index) => {
                        row.dataset.originalIndex = String(index);
                    });

                    table.querySelectorAll("[data-sort-header]").forEach((button) => {
                        button.addEventListener("click", () => {
                            const columnIndex = Number.parseInt(button.dataset.columnIndex, 10);
                            const currentDirection = button.dataset.sortDirection || "none";
                            const nextDirection = currentDirection === "asc" ? "desc" : "asc";
                            const rows = Array.from(tbody.rows);

                            table.querySelectorAll("[data-sort-header]").forEach((header) => {
                                header.dataset.sortDirection = "none";
                                header.closest("th")?.setAttribute("aria-sort", "none");
                                const icon = header.querySelector("[data-sort-icon]");
                                if (icon) icon.textContent = "sort";
                            });

                            button.dataset.sortDirection = nextDirection;
                            button.closest("th")?.setAttribute("aria-sort", nextDirection === "asc" ? "ascending" : "descending");
                            const activeIcon = button.querySelector("[data-sort-icon]");
                            if (activeIcon) activeIcon.textContent = nextDirection;

                            rows.sort((a, b) => {
                                const left = parseSortableValue(a.cells[columnIndex]?.textContent);
                                const right = parseSortableValue(b.cells[columnIndex]?.textContent);
                                const result = compareSortableValues(left, right, nextDirection);

                                if (result !== 0) return result;

                                return Number(a.dataset.originalIndex || 0) - Number(b.dataset.originalIndex || 0);
                            });

                            rows.forEach((row) => tbody.appendChild(row));
                        });
                    });
                });
            }

            async function init() {
                await loadECharts();
                buildDonut("donutChannels", "legendChannels");
                buildDonut("donutPlatforms", "legendPlatforms");
                buildFunnelHistoryDailyChart();
                buildFunnelHistoryDailyChart("opportunitiesSalesDailyChart", false);
                buildFunnelHistoryDailyChart("opportunitiesSalesDailyLineChart", false, "line");
                setupSortableTables();
                setupDatetimeMax();
                setInterval(setupDatetimeMax, 60 * 1000);
                window.addEventListener("resize", resizeCharts, { passive: true });
            }

            init();
        })();
    </script>
</x-app-layout>
