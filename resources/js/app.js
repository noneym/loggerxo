import {
    Chart,
    LineController,
    LineElement,
    BarController,
    BarElement,
    PointElement,
    LinearScale,
    CategoryScale,
    TimeScale,
    Tooltip,
    Filler,
    Legend,
} from 'chart.js';

Chart.register(LineController, LineElement, BarController, BarElement, PointElement, LinearScale, CategoryScale, TimeScale, Tooltip, Filler, Legend);

Chart.defaults.color = '#a1a1aa';
Chart.defaults.borderColor = 'rgba(255,255,255,0.06)';
Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
Chart.defaults.font.size = 11;
Chart.defaults.plugins.tooltip.backgroundColor = '#1a1a20';
Chart.defaults.plugins.tooltip.borderColor = 'rgba(255,255,255,0.08)';
Chart.defaults.plugins.tooltip.borderWidth = 1;
Chart.defaults.plugins.tooltip.titleColor = '#ececef';
Chart.defaults.plugins.tooltip.bodyColor = '#d4d4d8';
Chart.defaults.plugins.tooltip.padding = 10;
Chart.defaults.plugins.tooltip.cornerRadius = 8;
Chart.defaults.plugins.legend.display = false;
Chart.defaults.animation.duration = 300;

window.Chart = Chart;

const PALETTE = {
    error: '#f43f5e',
    warning: '#f59e0b',
    log: '#38bdf8',
    console: '#a78bfa',
    transaction: '#34d399',
    default: '#8b5cf6',
};

/**
 * Alpine component used as: x-data="lxChart({ type: 'bar', series: [...], labels: [...] })"
 * Re-renders whenever Livewire sends new data through the `data` prop.
 */
window.lxChart = function (config) {
    return {
        chart: null,
        init() {
            this.render(config);
            this.$watch('$wire.chartVersion', () => {
                const fresh = this.$el.dataset.chart;
                if (fresh) {
                    try { this.render(JSON.parse(fresh)); } catch (e) { /* ignore */ }
                }
            });
        },
        render(cfg) {
            const canvas = this.$refs.canvas;
            if (!canvas) { return; }
            if (this.chart) { this.chart.destroy(); }
            const datasets = (cfg.series || []).map((s, i) => {
                const color = s.color || PALETTE[s.key] || PALETTE.default;
                const base = {
                    label: s.label || s.key,
                    data: s.data,
                    borderColor: color,
                    backgroundColor: cfg.type === 'line' ? hexToRgba(color, 0.15) : hexToRgba(color, 0.75),
                    borderWidth: cfg.type === 'line' ? 2 : 0,
                    borderRadius: cfg.type === 'bar' ? 3 : 0,
                    fill: cfg.type === 'line',
                    tension: 0.35,
                    pointRadius: 0,
                    pointHitRadius: 10,
                    stack: cfg.stacked ? 'stack' : undefined,
                    yAxisID: s.axis || 'y',
                };
                return base;
            });
            this.chart = new Chart(canvas, {
                type: cfg.type || 'bar',
                data: { labels: cfg.labels || [], datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: !!cfg.legend, position: 'bottom', labels: { boxWidth: 8, boxHeight: 8, usePointStyle: true } },
                        tooltip: { callbacks: { label: (ctx) => ` ${ctx.dataset.label}: ${formatNumber(ctx.parsed.y)}${cfg.unit || ''}` } },
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { maxTicksLimit: 8, maxRotation: 0 }, stacked: !!cfg.stacked },
                        y: { beginAtZero: true, stacked: !!cfg.stacked, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { maxTicksLimit: 5, callback: (v) => formatNumber(v) }, border: { display: false } },
                        ...(cfg.y2 ? { y2: { position: 'right', beginAtZero: true, grid: { display: false }, ticks: { maxTicksLimit: 5 } } } : {}),
                    },
                },
            });
        },
    };
};

/**
 * Tiny inline sparkline: x-data="lxSparkline([1,2,3])"
 */
window.lxSparkline = function (points, color) {
    return {
        init() {
            const canvas = this.$refs.canvas;
            if (!canvas || !points || !points.length) { return; }
            new Chart(canvas, {
                type: 'bar',
                data: { labels: points.map((_, i) => i), datasets: [{ data: points, backgroundColor: hexToRgba(color || '#8b5cf6', 0.7), borderRadius: 1, barPercentage: 0.9, categoryPercentage: 1 }] },
                options: {
                    responsive: false,
                    animation: false,
                    plugins: { tooltip: { enabled: false }, legend: { display: false } },
                    scales: { x: { display: false }, y: { display: false, beginAtZero: true } },
                },
            });
        },
    };
};

function hexToRgba(hex, alpha) {
    const m = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    if (!m) { return hex; }
    return `rgba(${parseInt(m[1], 16)}, ${parseInt(m[2], 16)}, ${parseInt(m[3], 16)}, ${alpha})`;
}

function formatNumber(n) {
    if (n === null || n === undefined) { return '-'; }
    if (Math.abs(n) >= 1e6) { return (n / 1e6).toFixed(1) + 'M'; }
    if (Math.abs(n) >= 1e3) { return (n / 1e3).toFixed(1) + 'k'; }
    return Number.isInteger(n) ? n.toString() : n.toFixed(1);
}

// Copy-to-clipboard helper used by setup snippets.
window.lxCopy = async function (text, el) {
    try {
        await navigator.clipboard.writeText(text);
        if (el) {
            const original = el.textContent;
            el.textContent = 'Copied';
            setTimeout(() => { el.textContent = original; }, 1200);
        }
    } catch (e) { /* ignore */ }
};

// Relative time refresher for <time data-relative>
function refreshRelativeTimes() {
    document.querySelectorAll('time[data-relative]').forEach((el) => {
        const ts = Date.parse(el.getAttribute('datetime'));
        if (!ts) { return; }
        const diff = Math.round((Date.now() - ts) / 1000);
        let text;
        if (diff < 5) { text = 'just now'; }
        else if (diff < 60) { text = diff + 's ago'; }
        else if (diff < 3600) { text = Math.floor(diff / 60) + 'm ago'; }
        else if (diff < 86400) { text = Math.floor(diff / 3600) + 'h ago'; }
        else if (diff < 86400 * 30) { text = Math.floor(diff / 86400) + 'd ago'; }
        else { text = new Date(ts).toLocaleDateString(); }
        el.textContent = text;
    });
}
setInterval(refreshRelativeTimes, 15000);
document.addEventListener('livewire:navigated', refreshRelativeTimes);
document.addEventListener('DOMContentLoaded', refreshRelativeTimes);
