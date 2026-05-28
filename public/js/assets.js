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