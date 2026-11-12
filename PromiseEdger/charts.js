/* Admin analytics charts (Chart.js). Colors follow the active light/dark theme. */
(function () {
    'use strict';
    var D = window.PE_CHARTS; if (!D || !window.Chart) return;
    var charts = [];

    function v(name) { return getComputedStyle(document.documentElement).getPropertyValue(name).trim(); }
    function build() {
        charts.forEach(function (c) { c.destroy(); }); charts = [];
        var gold = '#C5A253', gold2 = '#D8CBAF', ink = v('--text'), muted = v('--muted'), grid = v('--border-soft'), ok = v('--ok'), bad = v('--bad');
        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
        Chart.defaults.color = muted;
        var axis = { grid: { color: grid }, ticks: { color: muted, maxRotation: 0, autoSkip: true, maxTicksLimit: 8 }, border: { display: false } };
        var yAxis = { grid: { color: grid }, ticks: { color: muted, precision: 0 }, beginAtZero: true, border: { display: false } };
        var base = { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: { labels: { usePointStyle: true, boxWidth: 8 } }, tooltip: { backgroundColor: ink, titleColor: v('--bg'), bodyColor: v('--bg'), padding: 10, cornerRadius: 8 } }, animation: { duration: 900, easing: 'easeOutQuart' } };
        function make(id, cfg) { var el = document.getElementById(id); if (el) charts.push(new Chart(el, cfg)); }

        make('chGrowth', { data: { labels: D.labels, datasets: [
            { type: 'line', label: D.text.users, data: D.users_total, borderColor: gold, backgroundColor: 'rgba(197,162,83,.15)', fill: true, tension: .35, cubicInterpolationMode: 'monotone', pointRadius: 0, borderWidth: 3, yAxisID: 'y' },
            { type: 'bar', label: D.text.new_users, data: D.users_new, backgroundColor: gold2, borderRadius: 5, yAxisID: 'y1' }] },
            options: Object.assign({}, base, { scales: { x: axis, y: yAxis, y1: { position: 'right', grid: { display: false }, ticks: { color: muted, precision: 0 }, beginAtZero: true, border: { display: false } } } }) });

        make('chStatus', { type: 'doughnut', data: { labels: D.status.labels, datasets: [{ data: D.status.data, backgroundColor: [gold, ok, bad], borderColor: v('--surface'), borderWidth: 3, hoverOffset: 8 }] },
            options: Object.assign({}, base, { cutout: '66%', plugins: Object.assign({}, base.plugins, { legend: { position: 'bottom', labels: { usePointStyle: true } } }) }) });

        make('chDaily', { data: { labels: D.labels, datasets: [
            { type: 'bar', label: D.text.created, data: D.promises, backgroundColor: gold, borderRadius: 5 },
            { type: 'line', label: D.text.completed, data: D.done, borderColor: ok, backgroundColor: ok, tension: .35, cubicInterpolationMode: 'monotone', pointRadius: 2, borderWidth: 2.5 }] },
            options: Object.assign({}, base, { scales: { x: axis, y: yAxis } }) });

        make('chCats', { type: 'bar', data: { labels: D.cats.labels, datasets: [{ data: D.cats.data, backgroundColor: [gold, gold2, '#8A6C22', ink, '#E6CF8E', '#A8894A'], borderRadius: 6 }] },
            options: Object.assign({}, base, { indexAxis: 'y', plugins: Object.assign({}, base.plugins, { legend: { display: false } }), scales: { x: yAxis, y: { grid: { display: false }, ticks: { color: muted }, border: { display: false } } } }) });

        make('chEng', { type: 'line', data: { labels: D.labels, datasets: [
            { label: D.text.reactions, data: D.reactions, borderColor: gold, backgroundColor: 'rgba(197,162,83,.18)', fill: true, tension: .35, cubicInterpolationMode: 'monotone', pointRadius: 0, borderWidth: 3 },
            { label: D.text.comments, data: D.comments, borderColor: ink, backgroundColor: 'transparent', tension: .35, cubicInterpolationMode: 'monotone', pointRadius: 0, borderWidth: 2, borderDash: [6, 4] }] },
            options: Object.assign({}, base, { scales: { x: axis, y: yAxis } }) });
    }
    build();
    window.addEventListener('pe:theme', function () { setTimeout(build, 60); });
})();
