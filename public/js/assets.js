document.addEventListener('DOMContentLoaded', function () {
    const chartDataNode = document.getElementById('ts-assets-chart-data');
    if (!chartDataNode || typeof Chart === 'undefined') {
        return;
    }

    if (typeof ChartDataLabels !== 'undefined' && !window.tsAssetsChartLabelsRegistered) {
        Chart.register(ChartDataLabels);
        window.tsAssetsChartLabelsRegistered = true;
    }

    [
        {
            id: 'ts-assets-town-chart',
            data: parseChartData(chartDataNode.dataset.townChart),
        },
        {
            id: 'ts-assets-manufacturer-chart',
            data: parseChartData(chartDataNode.dataset.manufacturerChart),
        },
    ].forEach(function (chart) {
        renderStackedChart(chart.id, chart.data);
    });

    const topSoftwaresRaw = chartDataNode.dataset.topSoftwaresChart;
    if (topSoftwaresRaw) {
        renderTopSoftwareChart('ts-assets-top-software-chart', topSoftwaresRaw);
    }

    const coverageRaw = chartDataNode.dataset.softwareCoverageChart;
    if (coverageRaw) {
        renderCoverageDonut('ts-assets-software-coverage-chart', coverageRaw);
    }
});

function parseChartData(rawValue) {
    if (!rawValue) {
        return { labels: [], datasets: [] };
    }

    try {
        return JSON.parse(rawValue);
    } catch (error) {
        console.error('Failed to parse assets chart data', error);
        return { labels: [], datasets: [] };
    }
}

function renderStackedChart(canvasId, data) {
    if (!data.labels || data.labels.length === 0) {
        return;
    }

    const canvas = document.getElementById(canvasId);
    if (!canvas) {
        return;
    }

    canvas.height = Math.max(260, data.labels.length * 34);

    new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: data.datasets,
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                },
                datalabels: {
                    display: false,
                },
            },
            scales: {
                x: {
                    stacked: true,
                    beginAtZero: true,
                },
                y: {
                    stacked: true,
                },
            },
        },
    });
}

function renderTopSoftwareChart(canvasId, rawValue) {
    let data;
    try {
        data = JSON.parse(rawValue);
    } catch (e) {
        return;
    }

    if (!data.labels || data.labels.length === 0) {
        return;
    }

    const canvas = document.getElementById(canvasId);
    if (!canvas) {
        return;
    }

    canvas.height = Math.max(260, data.labels.length * 30);

    new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: '',
                    data: data.values,
                    backgroundColor: '#C00000',
                },
            ],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                datalabels: {
                    display: true,
                    anchor: 'end',
                    align: 'end',
                    color: '#374151',
                    font: { size: 11 },
                    formatter: function (value) {
                        return value;
                    },
                },
            },
            scales: {
                x: { beginAtZero: true },
            },
        },
    });
}

function renderCoverageDonut(canvasId, rawValue) {
    let data;
    try {
        data = JSON.parse(rawValue);
    } catch (e) {
        return;
    }

    if (!data || data.total <= 0 || (data.with === 0 && data.without === 0)) {
        return;
    }

    const canvas = document.getElementById(canvasId);
    if (!canvas) {
        return;
    }

    new Chart(canvas.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: [
                canvas.dataset.labelWith || 'With software',
                canvas.dataset.labelWithout || 'Without software',
            ],
            datasets: [
                {
                    data: [data.with, data.without],
                    backgroundColor: ['#16a34a', '#ef4444'],
                    hoverOffset: 6,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'bottom' },
                datalabels: {
                    display: true,
                    color: '#fff',
                    font: { size: 13, weight: 'bold' },
                    formatter: function (value, ctx) {
                        const total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                        if (total === 0) return '';
                        return Math.round((value / total) * 100) + '%';
                    },
                },
            },
        },
    });
}