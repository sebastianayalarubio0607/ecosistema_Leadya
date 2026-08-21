<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-indigo-200">Dashboard Gerencial de Leads en LQ</h2>
        </div>
    </x-slot>

    <livewire:gerencial-leads-dashboard />
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

            function renderLegend(containerId, labels, values, keys, baseUrl, groupType, breakdownRows = []) {
                const container = document.getElementById(containerId);
                if (!container) return;

                container.innerHTML = "";

                const numberFormatter = new Intl.NumberFormat("es-CO");

                if (container.dataset.legendVariant === "breakdown-table" && breakdownRows.length) {
                    const table = document.createElement("table");
                    table.className = "min-w-full text-xs text-left";

                    const thead = document.createElement("thead");
                    thead.className = "text-white/50";
                    const headerRow = document.createElement("tr");
                    const dimensionTitle = groupType === "source" ?
                        "Source" :
                        (groupType === "plataforma" ? "Medio" : "Fuente");
                    const hasDimensionTotal = breakdownRows.some((item) =>
                        Object.prototype.hasOwnProperty.call(item, "dimension_total") && item.dimension_total !== null
                    );
                    const metricHeaders = hasDimensionTotal
                        ? [`Total ${dimensionTitle}`, "Leads en LQ calificados", "Leads en LQ no calificados"]
                        : ["Total", "Calif.", "No calif."];
                    [dimensionTitle, ...metricHeaders].forEach((title, index) => {
                        const th = document.createElement("th");
                        th.className = index === 0 ? "px-2 py-1 font-medium" :
                            "px-2 py-1 font-medium text-right";
                        th.textContent = title;
                        headerRow.appendChild(th);
                    });
                    thead.appendChild(headerRow);
                    table.appendChild(thead);

                    const tbody = document.createElement("tbody");
                    tbody.className = "divide-y divide-white/10";
                    const metricFields = hasDimensionTotal
                        ? ["dimension_total", "qualified", "unqualified"]
                        : ["total", "qualified", "unqualified"];
                    const columnTotals = Object.fromEntries(metricFields.map((field) => [field, 0]));

                    breakdownRows.forEach((item, index) => {
                        const key = item.key ?? keys[index];
                        const row = document.createElement("tr");
                        row.className = "rounded-lg";

                        if (key !== "__OTHER__") {
                            row.classList.add("cursor-pointer", "hover:bg-white/5");
                            row.addEventListener("click", () => goToGroup(baseUrl, groupType, key));
                        }

                        const source = document.createElement("td");
                        source.className = "px-2 py-2 text-white/85";

                        const sourceWrap = document.createElement("div");
                        sourceWrap.className = "flex items-center gap-2 min-w-0";

                        const dot = document.createElement("span");
                        dot.className = "h-3 w-3 rounded-sm shrink-0";
                        dot.style.background = COLORS[index % COLORS.length];

                        const name = document.createElement("span");
                        name.className = "truncate";
                        name.textContent = item.label ?? labels[index] ?? "";

                        sourceWrap.appendChild(dot);
                        sourceWrap.appendChild(name);
                        source.appendChild(sourceWrap);
                        row.appendChild(source);

                        metricFields.forEach((field) => {
                            const value = Number(item[field] ?? 0);
                            columnTotals[field] += Number.isFinite(value) ? value : 0;

                            const td = document.createElement("td");
                            td.className =
                                "px-2 py-2 text-right text-white/75 font-semibold tabular-nums";
                            td.textContent = numberFormatter.format(value);
                            row.appendChild(td);
                        });

                        tbody.appendChild(row);
                    });

                    table.appendChild(tbody);

                    const tfoot = document.createElement("tfoot");
                    const totalRow = document.createElement("tr");
                    totalRow.className = "border-t border-white/20 bg-white/5";

                    const totalLabel = document.createElement("td");
                    totalLabel.className = "px-2 py-2 text-white font-semibold";
                    totalLabel.textContent = "Total";
                    totalRow.appendChild(totalLabel);

                    metricFields.forEach((field) => {
                        const td = document.createElement("td");
                        td.className = "px-2 py-2 text-right text-white font-bold tabular-nums";
                        td.textContent = numberFormatter.format(columnTotals[field] ?? 0);
                        totalRow.appendChild(td);
                    });

                    tfoot.appendChild(totalRow);
                    table.appendChild(tfoot);
                    container.appendChild(table);
                    return;
                }

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

            function breakdownTotals(breakdownRows = []) {
                return breakdownRows.reduce((totals, item) => {
                    const qualified = Number(item.qualified ?? 0);
                    const unqualified = Number(item.unqualified ?? 0);
                    const rowTotal = Number(item.dimension_total ?? item.total ?? 0);

                    totals.qualified += Number.isFinite(qualified) ? qualified : 0;
                    totals.unqualified += Number.isFinite(unqualified) ? unqualified : 0;
                    totals.total += Number.isFinite(rowTotal) ? rowTotal : 0;

                    return totals;
                }, {
                    total: 0,
                    qualified: 0,
                    unqualified: 0
                });
            }

            function renderTotalsLegend(containerId, totals, dimensionTitle) {
                const container = document.getElementById(containerId);
                if (!container) return;

                const numberFormatter = new Intl.NumberFormat("es-CO");
                const table = document.createElement("table");
                table.className = "min-w-full text-xs text-left";
                const thead = document.createElement("thead");
                thead.className = "text-white/50";
                const headerRow = document.createElement("tr");

                [`Total ${dimensionTitle}`, "Leads en LQ calificados", "Leads en LQ no calificados"].forEach((title) => {
                    const th = document.createElement("th");
                    th.className = "px-2 py-1 font-medium text-right first:text-left";
                    th.textContent = title;
                    headerRow.appendChild(th);
                });

                thead.appendChild(headerRow);
                table.appendChild(thead);

                const tbody = document.createElement("tbody");
                tbody.className = "divide-y divide-white/10";
                const row = document.createElement("tr");
                row.className = "border-t border-white/20 bg-white/5";

                [totals.total, totals.qualified, totals.unqualified].forEach((value) => {
                    const td = document.createElement("td");
                    td.className = "px-2 py-2 text-right first:text-left text-white font-bold tabular-nums";
                    td.textContent = numberFormatter.format(value);
                    row.appendChild(td);
                });

                tbody.appendChild(row);
                table.appendChild(tbody);

                container.innerHTML = "";
                container.appendChild(table);
            }

            function buildTotalsDonut(canvasId, legendId) {
                const canvas = chartContainer(document.getElementById(canvasId));
                if (!canvas || !window.echarts) return;

                const breakdownRows = JSON.parse(canvas.dataset.breakdownRows || "[]");
                const dimensionTitle = canvas.dataset.dimensionTitle || "Source";
                const totals = breakdownTotals(breakdownRows);
                const values = [totals.qualified, totals.unqualified];
                const total = values.reduce((sum, value) => sum + value, 0);

                renderTotalsLegend(legendId, totals, dimensionTitle);
                if (total <= 0) return;

                const chartId = canvas.id;
                disposeChart(chartId);

                charts[chartId] = echarts.init(canvas, null, {
                    renderer: "canvas"
                });
                charts[chartId].setOption({
                    color: ["#22C55E", "#F59E0B"],
                    tooltip: {
                        trigger: "item",
                        backgroundColor: "rgba(15,23,42,.96)",
                        borderColor: "rgba(255,255,255,.12)",
                        textStyle: {
                            color: "#fff"
                        },
                        formatter: "{b}<br/><strong>{c}</strong> ({d}%)"
                    },
                    legend: {
                        bottom: 0,
                        icon: "roundRect",
                        textStyle: {
                            color: chartTextColor(".78")
                        }
                    },
                    series: [{
                        type: "pie",
                        radius: ["40%", "68%"],
                        center: ["50%", "42%"],
                        avoidLabelOverlap: false,
                        itemStyle: {
                            borderRadius: 0
                        },
                        label: {
                            show: false
                        },
                        emphasis: {
                            scale: true,
                            scaleSize: 2
                        },
                        data: [{
                                name: "Leads en LQ calificados",
                                value: totals.qualified
                            },
                            {
                                name: "Leads en LQ no calificados",
                                value: totals.unqualified
                            }
                        ]
                    }]
                });
            }

            function buildDonut(canvasId, legendId) {
                const canvas = chartContainer(document.getElementById(canvasId));
                if (!canvas || !window.echarts) return;

                const labels = JSON.parse(canvas.dataset.labels || "[]");
                const values = JSON.parse(canvas.dataset.values || "[]");
                const keys = JSON.parse(canvas.dataset.keys || "[]");
                const breakdownRows = JSON.parse(canvas.dataset.breakdownRows || "[]");
                const baseUrl = JSON.parse(canvas.dataset.baseUrl || '""');
                const groupType = canvas.dataset.groupType;

                if (!labels.length || !values.length) return;

                disposeChart(canvasId);

                renderLegend(legendId, labels, values, keys, baseUrl, groupType, breakdownRows);

                charts[canvasId] = echarts.init(canvas, null, {
                    renderer: "canvas"
                });
                charts[canvasId].setOption({
                    color: COLORS,
                    tooltip: {
                        trigger: "item",
                        backgroundColor: "rgba(15,23,42,.96)",
                        borderColor: "rgba(255,255,255,.12)",
                        textStyle: {
                            color: "#fff"
                        },
                        formatter: "{b}<br/><strong>{c}</strong> ({d}%)"
                    },
                    legend: {
                        show: false
                    },
                    series: [{

                        type: 'pie',
                        radius: ['40%', '70%'],
                        avoidLabelOverlap: false,
                        padAngle: 0,
                        itemStyle: {
                            borderRadius: 0
                        },
                        label: {
                            show: false
                        },
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
                            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{
                                    offset: 0,
                                    color
                                },
                                {
                                    offset: 1,
                                    color: "rgba(255,255,255,0)"
                                }
                            ])
                        } : undefined,
                        emphasis: {
                            focus: "series"
                        }
                    };
                });

                charts[chartId] = echarts.init(canvas, null, {
                    renderer: "canvas"
                });
                charts[chartId].setOption({
                    color: COLORS,
                    tooltip: {
                        trigger: "axis",
                        backgroundColor: "rgba(15,23,42,.96)",
                        borderColor: "rgba(255,255,255,.12)",
                        textStyle: {
                            color: "#fff"
                        },
                        axisPointer: {
                            type: type === "line" ? "line" : "shadow",
                            lineStyle: {
                                color: "rgba(255,255,255,.25)"
                            },
                            shadowStyle: {
                                color: "rgba(255,255,255,.06)"
                            }
                        }
                    },
                    legend: {
                        top: 0,
                        right: 0,
                        icon: "roundRect",
                        textStyle: {
                            color: chartTextColor(".78")
                        }
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
                        axisLabel: {
                            color: chartTextColor(".68")
                        },
                        axisLine: {
                            lineStyle: {
                                color: "rgba(255,255,255,.12)"
                            }
                        },
                        axisTick: {
                            show: false
                        },
                        splitLine: {
                            show: false
                        }
                    },
                    yAxis: {
                        type: "value",
                        minInterval: 1,
                        axisLabel: {
                            color: chartTextColor(".68")
                        },
                        axisLine: {
                            show: false
                        },
                        axisTick: {
                            show: false
                        },
                        splitLine: {
                            lineStyle: {
                                color: "rgba(255,255,255,.08)"
                            }
                        }
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
                const inputs = document.querySelectorAll(
                    'input[type="datetime-local"][name="from"], input[type="datetime-local"][name="to"]');
                inputs.forEach((input) => {
                    input.max = max;
                });
            }

            let listenersAttached = false;
            let initScheduled = false;

            function scheduleInit() {
                if (initScheduled) {
                    return;
                }

                initScheduled = true;
                window.requestAnimationFrame(() => {
                    initScheduled = false;
                    init();
                });
            }

            async function init() {
                await loadECharts();
                buildDonut("donutSources", "legendSources");
                buildDonut("donutPlatforms", "legendPlatforms");
                buildTotalsDonut("donutSourceTotals", "legendSourceTotals");
                buildTotalsDonut("donutPlatformTotals", "legendPlatformTotals");
                buildFunnelHistoryDailyChart();
                buildFunnelHistoryDailyChart("opportunitiesSalesDailyChart", false);
                buildFunnelHistoryDailyChart("opportunitiesSalesDailyLineChart", false, "line");
                setupDatetimeMax();

                if (!listenersAttached) {
                    listenersAttached = true;
                    window.addEventListener("resize", resizeCharts, {
                        passive: true
                    });

                    document.addEventListener("livewire:init", () => {
                        Livewire.hook("morph.updated", () => {
                            scheduleInit();
                        });
                    });

                    document.addEventListener("gerencial-leads-refresh", () => {
                        scheduleInit();
                    });

                    document.addEventListener("focusin", (event) => {
                        if (event.target?.matches?.('input[type="datetime-local"]')) {
                            setupDatetimeMax();
                        }
                    });
                }
            }

            init();
        })();
    </script>

</x-app-layout>
