import { BarChart, PieChart } from 'echarts/charts';
import { GridComponent, LegendComponent, TooltipComponent } from 'echarts/components';
import * as echarts from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';

echarts.use([BarChart, PieChart, GridComponent, LegendComponent, TooltipComponent, CanvasRenderer]);

const charts = new Map();
const parse = (element) => {
    try {
        return JSON.parse(element.dataset.chart || '{}');
    } catch {
        return {};
    }
};

const donut = (rows) => ({
    tooltip: { trigger: 'item' },
    legend: { bottom: 0, textStyle: { color: 'rgba(255,255,255,.72)' } },
    series: [{ type: 'pie', radius: ['48%', '72%'], center: ['50%', '42%'], label: { color: 'rgba(255,255,255,.82)' }, data: Array.isArray(rows) ? rows : [] }],
});

const stacked = (payload, stack = 'stack') => ({
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
    legend: { bottom: 0, textStyle: { color: 'rgba(255,255,255,.72)' } },
    grid: { top: 18, right: 12, bottom: 64, left: 44, containLabel: true },
    xAxis: { type: 'category', data: payload.labels || [], axisLabel: { color: 'rgba(255,255,255,.68)' }, axisLine: { lineStyle: { color: 'rgba(255,255,255,.15)' } } },
    yAxis: { type: 'value', axisLabel: { color: 'rgba(255,255,255,.68)' }, splitLine: { lineStyle: { color: 'rgba(255,255,255,.10)' } } },
    series: payload.series
        ? payload.series.map((serie) => ({ name: serie.name, type: 'bar', stack, data: serie.data || [] }))
        : [
            { name: 'Leads Calificados', type: 'bar', stack, data: payload.qualified || [] },
            { name: 'Leads No Calificados', type: 'bar', stack, data: payload.unqualified || [] },
        ],
});

const mountCharts = () => {
    document.querySelectorAll('[data-general-chart]').forEach((element) => {
        charts.get(element)?.dispose();
        const chart = echarts.init(element);
        const type = element.dataset.chartType;
        const payload = parse(element);
        chart.setOption(type === 'donut' ? donut(payload) : stacked(payload, type === 'funnel-daily' ? 'funnels' : 'qualification'));
        charts.set(element, chart);
    });
};

window.addEventListener('resize', () => charts.forEach((chart) => chart.resize()));
document.addEventListener('DOMContentLoaded', mountCharts);
document.addEventListener('livewire:navigated', mountCharts);
