/**
 * TicketsStatistics — Central Dashboard Stats Widget
 * Renders ticket & asset charts in Tab 0 of /front/central.
 */
(function () {
    'use strict';

    // Chart instances keyed by canvas id
    const charts = {};

    // Status colors matching TicketsStatistics::getStatusColor()
    const STATUS_COLORS = {
        New: '#49bf4d',
        Assigned: '#49bf4d',
        Pending: '#ffa500',
        Solved: '#C00000',
        Closed: '#C00000',
    };

    // Fixed asset palette (HSL steps)
    const ASSET_COLORS = [
        '#3498db', '#2ecc71', '#e67e22', '#9b59b6',
        '#1abc9c', '#e74c3c', '#34495e',
    ];

    // Doughnut status colors derived from labels returned by the server
    const STATUS_COLOR_ORDER = [
        '#49bf4d', // New
        '#49bf4d', // Assigned
        '#ffa500', // Pending
        '#C00000', // Solved
        '#888888', // Closed
    ];

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    function destroyChart(id) {
        if (charts[id]) {
            charts[id].destroy();
            delete charts[id];
        }
    }

    function getAjaxUrl(period) {
        return CFG_GLPI.root_doc
            + '/plugins/ticketsstatistics/ajax/central_data.php'
            + '?period=' + encodeURIComponent(period);
    }

    // -----------------------------------------------------------------------
    // Counter cards
    // -----------------------------------------------------------------------

    function updateCounters(ticketStatus) {
        const wrapper = document.getElementById('ts-c-counters');
        if (!wrapper) return;
        const total = ticketStatus.values.reduce((a, b) => a + b, 0);
        wrapper.querySelectorAll('[data-status-index]').forEach(function (el) {
            const idx = parseInt(el.dataset.statusIndex, 10);
            el.textContent = ticketStatus.values[idx] !== undefined
                ? ticketStatus.values[idx]
                : 0;
        });
        const totalEl = wrapper.querySelector('[data-status-total]');
        if (totalEl) totalEl.textContent = total;
    }

    // -----------------------------------------------------------------------
    // Chart renderers
    // -----------------------------------------------------------------------

    function renderTicketStatusChart(data) {
        destroyChart('ts-c-chart-status');
        const canvas = document.getElementById('ts-c-chart-status');
        if (!canvas) return;
        charts['ts-c-chart-status'] = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.values,
                    backgroundColor: STATUS_COLOR_ORDER.slice(0, data.labels.length),
                }],
            },
            options: {
                plugins: {
                    legend: { position: 'right' },
                    datalabels: {
                        color: '#fff',
                        formatter: function (value) {
                            return value > 0 ? value : '';
                        },
                    },
                },
                maintainAspectRatio: false,
            },
        });
    }

    function renderTopRequestersChart(data) {
        destroyChart('ts-c-chart-requesters');
        const canvas = document.getElementById('ts-c-chart-requesters');
        if (!canvas) return;
        // Horizontal bar: reverse so highest is on top
        const labels = data.labels.slice().reverse();
        const values = data.values.slice().reverse();
        charts['ts-c-chart-requesters'] = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: tsTranslations.topRequesters,
                    data: values,
                    backgroundColor: '#49bf4d',
                }],
            },
            options: {
                indexAxis: 'y',
                plugins: {
                    legend: { display: false },
                    datalabels: {
                        anchor: 'end',
                        align: 'end',
                        color: '#333',
                        formatter: function (value) {
                            return value > 0 ? value : '';
                        },
                    },
                },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 } },
                },
                maintainAspectRatio: false,
            },
        });
    }

    function renderTicketsByTownChart(data) {
        destroyChart('ts-c-chart-towns');
        const canvas = document.getElementById('ts-c-chart-towns');
        if (!canvas) return;
        charts['ts-c-chart-towns'] = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: tsTranslations.ticketsByTown,
                    data: data.values,
                    backgroundColor: '#3498db',
                }],
            },
            options: {
                plugins: {
                    legend: { display: false },
                    datalabels: {
                        anchor: 'end',
                        align: 'end',
                        color: '#333',
                        formatter: function (value) {
                            return value > 0 ? value : '';
                        },
                    },
                },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                },
                maintainAspectRatio: false,
            },
        });
    }

    function renderAssetsByTypeChart(data) {
        destroyChart('ts-c-chart-assets');
        const canvas = document.getElementById('ts-c-chart-assets');
        if (!canvas) return;
        charts['ts-c-chart-assets'] = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.values,
                    backgroundColor: ASSET_COLORS.slice(0, data.labels.length),
                }],
            },
            options: {
                plugins: {
                    legend: { position: 'right' },
                    datalabels: {
                        color: '#fff',
                        formatter: function (value) {
                            return value > 0 ? value : '';
                        },
                    },
                },
                maintainAspectRatio: false,
            },
        });
    }

    // -----------------------------------------------------------------------
    // Main loader
    // -----------------------------------------------------------------------

    function loadCentralStats(period) {
        const spinner = document.getElementById('ts-c-spinner');
        if (spinner) {
            spinner.classList.remove('d-none');
            spinner.classList.add('d-flex');
        }

        fetch(getAjaxUrl(period))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                Chart.register(ChartDataLabels);
                updateCounters(data.ticketStatus);
                renderTicketStatusChart(data.ticketStatus);
                renderTopRequestersChart(data.topRequesters);
                renderTicketsByTownChart(data.ticketsByTown);
                renderAssetsByTypeChart(data.assetsByType);
            })
            .catch(function (err) {
                console.error('[TicketsStatistics] central_stats fetch error', err);
            })
            .finally(function () {
                if (spinner) {
                    spinner.classList.add('d-none');
                    spinner.classList.remove('d-flex');
                }
            });
    }

    // -----------------------------------------------------------------------
    // Hide the native GLPI dashboard grid
    // -----------------------------------------------------------------------

    function hideGlpiDashboardGrid() {
        // The GLPI grid renders as a .grid-stack or .dashboard-grid wrapper
        // inside the tab-pane. We target the first .dashboard or .grid-stack
        // that is NOT our own stats wrapper.
        const selectors = [
            '.tab-content .dashboard',
            '.tab-content .grid-stack',
            '#dashboard-central',
        ];
        let found = false;
        selectors.forEach(function (sel) {
            document.querySelectorAll(sel).forEach(function (el) {
                if (!el.closest('#ts-central-stats')) {
                    el.style.display = 'none';
                    found = true;
                }
            });
        });
        return found;
    }

    function watchForGlpiGrid() {
        if (hideGlpiDashboardGrid()) return;
        const obs = new MutationObserver(function () {
            if (hideGlpiDashboardGrid()) obs.disconnect();
        });
        obs.observe(document.documentElement, { childList: true, subtree: true });
    }

    // -----------------------------------------------------------------------
    // Boot
    // -----------------------------------------------------------------------

    let booted = false;

    function boot() {
        if (booted) return;
        if (!document.getElementById('ts-central-stats')) return;
        booted = true;

        watchForGlpiGrid();

        const periodSel = document.getElementById('ts-c-period');
        if (periodSel) {
            periodSel.addEventListener('change', function () {
                loadCentralStats(this.value);
            });
            loadCentralStats(periodSel.value);
        }
    }

    // Try immediately (server-rendered active tab)
    document.addEventListener('DOMContentLoaded', function () {
        boot();

        // Watch for element injected later via GLPI's AJAX tab loading
        if (!booted) {
            const obs = new MutationObserver(function () {
                boot();
                if (booted) obs.disconnect();
            });
            obs.observe(document.body, { childList: true, subtree: true });
        }
    });
})();
